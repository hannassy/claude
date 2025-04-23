<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Client;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Logger\Monolog;
use Magento\Setup\Exception;
use Tirehub\Punchout\Model\Config;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Model\CxmlProcessor;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Api\EnablePunchoutModeInterface;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Tirehub\Punchout\Api\CreateCustomerInterface;
use Tirehub\Punchout\Model\Validator\Dealer as DealerValidator;

class DefaultClient extends AbstractClient implements ClientInterface
{
    public function __construct(
        protected readonly RawFactory $rawFactory,
        protected readonly RedirectFactory $redirectFactory,
        protected readonly UrlInterface $urlBuilder,
        protected readonly Config $config,
        protected readonly CustomerRepositoryInterface $customerRepository,
        protected readonly CustomerSession $customerSession,
        protected readonly SessionFactory $sessionFactory,
        protected readonly SessionResource $sessionResource,
        protected readonly CxmlProcessor $cxmlProcessor,
        protected readonly Monolog $logger,
        protected readonly CreateCustomerInterface $createCustomer,
        protected readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        protected readonly EnablePunchoutModeInterface $enablePunchoutMode,
        protected readonly DealerValidator $dealerValidator
    ) {
        parent::__construct(
            $sessionFactory,
            $getPunchoutPartnersManagement,
            $sessionResource
        );
    }

    public function processItem(RequestInterface $request)
    {
        $result = $this->rawFactory->create();
        return $result->setHttpResponseCode(200);
    }

    public function processRequest(RequestInterface $request)
    {
        try {
            $content = $request->getContent();
            $parsedData = $this->cxmlProcessor->parseRequest($content);

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

            // If no addressID in shipToAddress, redirect to portal for selection
            if (!$addressId) {
                $this->logger->info('Punchout: No valid addressID found, redirecting to portal');

                // Generate portal URL with the session buyer_cookie
                $portalUrl = $this->urlBuilder->getUrl('punchout/portal', ['cookie' => $buyerCookie]);

                // Generate response with portal URL
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

            // Generate response with shopping URL
            $result = $this->rawFactory->create();
            $shoppingUrl = $this->urlBuilder->getUrl('punchout/shopping/start', ['cookie' => $buyerCookie]);

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

    public function processShoppingStart(RequestInterface $request)
    {
        try {
            $buyerCookie = $request->getParam('cookie');

            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }

            // Load session by buyer cookie
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid buyer cookie'));
            }

            // Get customer ID from session
            $customerId = $session->getData(SessionInterface::CUSTOMER_ID);

            // If we have a customer ID, log them in
            if ($customerId) {
                $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_ACTIVE);
                $this->sessionResource->save($session);

                // Log in the customer
                $customer = $this->customerRepository->getById($customerId);
                $this->customerSession->setCustomerDataAsLoggedIn($customer);
                $this->customerSession->regenerateId();

                // Enable punchout mode
                $this->enablePunchoutMode->execute($buyerCookie);

                $this->logger->info('Punchout: Customer logged in: ' . $customerId);
            }

            // Redirect to home page
            $result = $this->redirectFactory->create();
            $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
            $result->setHeader('Pragma', 'no-cache', true);
            return $result->setPath('/');
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error during shopping start: ' . $e->getMessage());
            $result = $this->redirectFactory->create();
            return $result->setPath('customer/account/login');
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error during shopping start: ' . $e->getMessage());
            $result = $this->redirectFactory->create();
            return $result->setPath('customer/account/login');
        }
    }

    public function processPortalAddressSubmit(RequestInterface $request)
    {
        try {
            $buyerCookie = $request->getParam('cookie');
            $addressId = $request->getParam('locationId');

            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }

            if (empty($addressId)) {
                throw new LocalizedException(__('Missing address_id parameter'));
            }

            // Load session
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid buyer cookie'));
            }

            // Get extrinsics from session
            $extrinsics = [];
            if ($session->getData(SessionInterface::EMAIL)) {
                $extrinsics['UserEmail'] = $session->getData(SessionInterface::EMAIL);
            }
            if ($session->getData(SessionInterface::FULL_NAME)) {
                $extrinsics['UserFullName'] = $session->getData(SessionInterface::FULL_NAME);
            }
            if ($session->getData(SessionInterface::FIRST_NAME)) {
                $extrinsics['FirstName'] = $session->getData(SessionInterface::FIRST_NAME);
            }
            if ($session->getData(SessionInterface::LAST_NAME)) {
                $extrinsics['LastName'] = $session->getData(SessionInterface::LAST_NAME);
            }
            if ($session->getData(SessionInterface::PHONE)) {
                $extrinsics['PhoneNumber'] = $session->getData(SessionInterface::PHONE);
            }

            // Create customer using the selected address ID
            $customerId = $this->createCustomer->execute($extrinsics, $addressId);

            // Update session with customer ID
            $session->setData(SessionInterface::CUSTOMER_ID, $customerId);
            $this->sessionResource->save($session);

            // Redirect to shopping start
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/shopping/start', ['cookie' => $buyerCookie]);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing portal address submit: ' . $e->getMessage());

            // Redirect back to portal with error
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/portal', [
                'cookie' => $buyerCookie,
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal address submit: ' . $e->getMessage());

            // Redirect back to portal with error
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/portal', [
                'cookie' => $buyerCookie,
                'error' => 'Unexpected error occurred'
            ]);
        }
    }
}
