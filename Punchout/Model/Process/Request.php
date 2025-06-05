<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Api\CreateCustomerInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\CxmlProcessor;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Tirehub\Punchout\Service\TokenGenerator;
use Tirehub\Punchout\Model\LogFactory;

class Request
{
    public const CONTENT_TYPE_TEXT_XML = 'text/xml';

    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly CxmlProcessor $cxmlProcessor,
        private readonly SessionFactory $sessionFactory,
        private readonly SessionResource $sessionResource,
        private readonly CreateCustomerInterface $createCustomer,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Monolog $logger,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly LogFactory $logFactory
    ) {
    }

    public function execute(RequestInterface $request): ResultInterface
    {
        $buyerCookie = null;
        $log = $this->logFactory->create();

        try {
            $content = $request->getContent();

            $log->logInfo('Processing punchout request', [
                'content_length' => strlen($content),
                'request_method' => $request->getMethod()
            ]);

            try {
                $parsedData = $this->cxmlProcessor->parseRequest($content);
                $buyerCookie = $parsedData['buyer_cookie'] ?? null;

                $log->logInfo('Successfully parsed cXML request', [
                    'buyer_cookie' => $buyerCookie,
                    'sender_identity' => $parsedData['sender']['identity'] ?? '',
                    'has_address_id' => !empty($parsedData['address_id'])
                ], $buyerCookie);

            } catch (LocalizedException $e) {
                if (str_contains($e->getMessage(), 'Security violation: This buyer cookie has already been used')) {
                    $this->logger->warning('Punchout: Security violation - buyer cookie reuse detected');
                    $log->logCritical('Security violation - buyer cookie reuse detected', [
                        'error' => $e->getMessage()
                    ], $buyerCookie);

                    $result = $this->rawFactory->create();
                    $responseXml = $this->cxmlProcessor->generateBuyerCookieReuseResponse();
                    $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
                    $result->setHttpResponseCode(403);
                    $result->setContents($responseXml);
                    return $result;
                }
                throw $e;
            }

            $log->logDebug('Validating credentials', [
                'domain' => $parsedData['sender']['domain'],
                'identity' => $parsedData['sender']['identity']
            ], $buyerCookie);

            $this->cxmlProcessor->validateCredentials(
                $parsedData['sender']['domain'],
                $parsedData['sender']['identity'],
                $parsedData['sender']['shared_secret']
            );

            $log->logInfo('Credentials validated successfully', [], $buyerCookie);

            $extrinsics = $parsedData['extrinsics'] ?? [];
            $browserFormPostUrl = $parsedData['browser_form_post_url'] ?? '';
            $addressId = $parsedData['address_id'] ?? null;
            $identity = $parsedData['sender']['identity'] ?? '';

            $session = $this->saveSession(
                $buyerCookie,
                $identity,
                $extrinsics,
                $browserFormPostUrl,
                $addressId
            );

            if (isset($parsedData['cxml_request'])) {
                $session->setData('cxml_request', $parsedData['cxml_request']);
                $this->sessionResource->save($session);
            }

            if (!$addressId) {
                $this->logger->info('Punchout: No valid addressID found, redirecting to portal');
                $log->logInfo('No valid addressID found, redirecting to portal', [
                    'session_id' => $session->getId()
                ], $buyerCookie);

                $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);

                $result = $this->rawFactory->create();
                $responseXml = $this->cxmlProcessor->generateSuccessResponse($portalUrl);

                $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
                $result->setContents($responseXml);

                return $result;
            }

            $log->logInfo('Creating customer with address ID', [
                'address_id' => $addressId
            ], $buyerCookie);

            $customerId = $this->createCustomer->execute($extrinsics, $addressId);

            $log->logInfo('Customer created successfully', [
                'customer_id' => $customerId,
                'address_id' => $addressId
            ], $buyerCookie);

            $session->setData(SessionInterface::CUSTOMER_ID, $customerId);
            $this->sessionResource->save($session);

            $result = $this->rawFactory->create();
            $shoppingUrl = $this->tokenGenerator->generateShoppingStartUrl($buyerCookie);

            $responseXml = $this->cxmlProcessor->generateSuccessResponse($shoppingUrl);

            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
            $result->setContents($responseXml);

            $log->logInfo('Punchout request completed successfully', [
                'redirect_url' => $shoppingUrl
            ], $buyerCookie);

            return $result;
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing request: ' . $e->getMessage());
            $log->logError('Error processing request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], $buyerCookie);

            // Rest of error handling...
            $result = $this->rawFactory->create();
            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);

            if (str_contains($e->getMessage(), 'Unable to find identity match')) {
                $responseXml = $this->cxmlProcessor->generateInvalidIdentityResponse();
                $result->setHttpResponseCode(400);
            } elseif (str_contains($e->getMessage(), 'Invalid shared secret')) {
                $responseXml = $this->cxmlProcessor->generateInvalidSharedSecretResponse();
                $result->setHttpResponseCode(401);
            } elseif (str_contains($e->getMessage(), 'Unable to match requested address id')) {
                preg_match('/address id ([^\s]+) to/', $e->getMessage(), $matches);
                $dealerCode = $matches[1] ?? '';
                $responseXml = $this->cxmlProcessor->generateInvalidDealerCodeResponse($dealerCode);
                $result->setHttpResponseCode(400);
            } elseif (str_contains($e->getMessage(), 'not currently authorized')) {
                preg_match('/location ([^\s]+) Is/', $e->getMessage(), $matches);
                $dealerCode = $matches[1] ?? '';
                $responseXml = $this->cxmlProcessor->generateUnauthorizedDealerResponse($dealerCode);
                $result->setHttpResponseCode(401);
            } elseif (str_contains($e->getMessage(), 'missing required attributes')) {
                $responseXml = $this->cxmlProcessor->generateInvalidXmlResponse();
                $result->setHttpResponseCode(500);
            } else {
                $responseXml = $this->cxmlProcessor->generateErrorResponse('400', $e->getMessage());
                $result->setHttpResponseCode(400);
            }

            $result->setContents($responseXml);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error: ' . $e->getMessage());
            $log->logCritical('Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], $buyerCookie);

            $result = $this->rawFactory->create();
            $responseXml = $this->cxmlProcessor->generateErrorResponse('500', 'Internal Server Error');

            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
            $result->setHttpResponseCode(500);
            $result->setContents($responseXml);

            return $result;
        }
    }

    private function saveSession(
        string $buyerCookie,
        string $identity,
        array $extrinsics,
        string $browserFormPostUrl,
        ?string $addressId
    ) {
        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);
        $session->setData(SessionInterface::PARTNER_IDENTITY, $identity);

        if (!$session->getId()) {
            $session->setData(SessionInterface::BUYER_COOKIE, $buyerCookie);
            $session->setData(SessionInterface::CLIENT_TYPE, 'default');
            $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_NEW);
        }

        // Get corp address ID from partners management based on identity
        $corpAddressId = $this->getCorpAddressId($identity);

        if ($corpAddressId) {
            $session->setData(SessionInterface::CORP_ADDRESS_ID, $corpAddressId);
        }

        // Set user data
        $email = $extrinsics['UserEmail'] ?? null;
        $fullName = $extrinsics['UserFullName'] ?? null;
        $firstName = $extrinsics['FirstName'] ?? null;
        $lastName = $extrinsics['LastName'] ?? null;
        $phone = $extrinsics['PhoneNumber'] ?? null;

        if ($fullName) {
            $session->setData(SessionInterface::FULL_NAME, $fullName);
        }

        if ($firstName) {
            $session->setData(SessionInterface::FIRST_NAME, $firstName);
        }

        if ($lastName) {
            $session->setData(SessionInterface::LAST_NAME, $lastName);
        }

        if ($phone) {
            $session->setData(SessionInterface::PHONE, $phone);
        }

        // Set browser form post URL and ship to address
        if ($browserFormPostUrl) {
            $session->setData(SessionInterface::BROWSER_FORM_POST_URL, $browserFormPostUrl);
        }

        if ($addressId) {
            $session->setData(SessionInterface::ADDRESS_ID, $addressId);
        }

        $this->sessionResource->save($session);

        return $session;
    }

    private function getCorpAddressId(string $identity): ?string
    {
        $identity = strtolower($identity);
        $result = $this->getPunchoutPartnersManagement->getResult();

        foreach ($result as $partner) {
            $itemIdentity = strtolower($partner['identity'] ?? '');
            if ($itemIdentity === $identity) {
                return $partner['corpAddressId'] ?? null;
            }
        }

        return null;
    }
}
