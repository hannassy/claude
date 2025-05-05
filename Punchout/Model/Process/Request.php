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
use Tirehub\Punchout\Service\TokenGenerator;

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
        private readonly \Tirehub\Punchout\Service\GetPunchoutPartnersManagement $getPunchoutPartnersManagement
    ) {
    }

    /**
     * Process request for punchout
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function execute(RequestInterface $request): ResultInterface
    {
        try {
            $content = $request->getContent();

            try {
                $parsedData = $this->cxmlProcessor->parseRequest($content);
            } catch (LocalizedException $e) {
                // Check if the exception is specifically for buyer cookie reuse
                if (str_contains($e->getMessage(), 'Security violation: This buyer cookie has already been used')) {
                    $this->logger->warning('Punchout: Security violation - buyer cookie reuse detected');
                    $result = $this->rawFactory->create();
                    $responseXml = $this->cxmlProcessor->generateBuyerCookieReuseResponse();
                    $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
                    $result->setHttpResponseCode(403);
                    $result->setContents($responseXml);
                    return $result;
                }
                throw $e;
            }

            // Validate credentials
            $this->cxmlProcessor->validateCredentials(
                $parsedData['sender']['domain'],
                $parsedData['sender']['identity'],
                $parsedData['sender']['shared_secret']
            );

            // Extract data
            $buyerCookie = $parsedData['buyer_cookie'];
            $extrinsics = $parsedData['extrinsics'] ?? [];
            $browserFormPostUrl = $parsedData['browser_form_post_url'] ?? '';
            $addressId = $parsedData['address_id'] ?? null;
            $identity = $parsedData['sender']['identity'] ?? '';

            // Create session
            $session = $this->saveSession(
                $buyerCookie,
                $identity,
                $extrinsics,
                $browserFormPostUrl,
                $addressId
            );

            // If in debug mode and cXML request is available, save it
            if (isset($parsedData['cxml_request'])) {
                $session->setData('cxml_request', $parsedData['cxml_request']);
                $this->sessionResource->save($session);
            }

            // If no addressID in shipToAddress, redirect to portal for selection
            if (!$addressId) {
                $this->logger->info('Punchout: No valid addressID found, redirecting to portal');

                // Generate secure portal URL with the session buyer_cookie
                $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);

                // Generate response with secure portal URL
                $result = $this->rawFactory->create();
                $responseXml = $this->cxmlProcessor->generateSuccessResponse($portalUrl);

                $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
                $result->setContents($responseXml);

                return $result;
            }

            // If we have a valid addressId, create customer
            $customerId = $this->createCustomer->execute($extrinsics, $addressId);

            // Update session with customer ID
            $session->setData(SessionInterface::CUSTOMER_ID, $customerId);
            $this->sessionResource->save($session);

            // Generate response with secure shopping URL
            $result = $this->rawFactory->create();
            $shoppingUrl = $this->tokenGenerator->generateShoppingStartUrl($buyerCookie);

            $responseXml = $this->cxmlProcessor->generateSuccessResponse($shoppingUrl);

            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
            $result->setContents($responseXml);

            return $result;
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing request: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $responseXml = $this->cxmlProcessor->generateErrorResponse('400', $e->getMessage());

            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
            $result->setHttpResponseCode(400);
            $result->setContents($responseXml);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $responseXml = $this->cxmlProcessor->generateErrorResponse('500', 'Internal Server Error');

            $result->setHeader('Content-Type', self::CONTENT_TYPE_TEXT_XML);
            $result->setHttpResponseCode(500);
            $result->setContents($responseXml);

            return $result;
        }
    }

    /**
     * Save session data
     *
     * @param string $buyerCookie
     * @param string $identity
     * @param array $extrinsics
     * @param string $browserFormPostUrl
     * @param string|null $addressId
     * @return \Tirehub\Punchout\Model\Session
     */
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

    /**
     * Get corp address ID from partner configuration
     *
     * @param string $identity
     * @return string|null
     */
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
