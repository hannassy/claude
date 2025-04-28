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
        protected readonly DealerValidator $dealerValidator,
        protected readonly \Tirehub\Punchout\Model\ItemFactory $itemFactory,
        protected readonly \Tirehub\Punchout\Model\ResourceModel\Item $itemResource,
        protected readonly \Tirehub\Punchout\Model\ResourceModel\Item\CollectionFactory $itemCollectionFactory,
        protected readonly \Magento\Checkout\Model\Cart $cart,
        protected readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        parent::__construct(
            $sessionFactory,
            $getPunchoutPartnersManagement,
            $sessionResource
        );
    }

    /**
     * Process item request for punchout
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function processItem(RequestInterface $request)
    {
        try {
            // Get required parameters
            $partnerIdentity = $request->getParam('partnerIdentity');
            $dealerCode = $request->getParam('dealerCode');
            $itemId = $request->getParam('itemId');
            $quantityNeeded = (int)$request->getParam('quantityNeeded', 1);

            // Validate required parameters
            if (empty($partnerIdentity) || empty($dealerCode)) {
                throw new LocalizedException(__('Partner Identity and Dealer Code are required parameters'));
            }

            $this->logger->info('Punchout: Processing item request', [
                'partnerIdentity' => $partnerIdentity,
                'dealerCode' => $dealerCode,
                'itemId' => $itemId,
                'quantityNeeded' => $quantityNeeded
            ]);

            // Validate dealer code
            try {
                $this->dealerValidator->execute($dealerCode, $partnerIdentity);
            } catch (\Exception $e) {
                $this->logger->error('Punchout: Dealer validation failed: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid dealer code or partner identity'));
            }

            // Generate a unique buyerCookie (token) for this punchout session
            // This will be used as the buyerCookie in subsequent steps
            $buyerCookie = hash('sha256', $partnerIdentity . $dealerCode . uniqid('', true));

            // Check if we have multiple items (comma-separated)
            $itemIds = $itemId ? explode(',', $itemId) : [];

            if (!empty($itemIds)) {
                // Process each item
                foreach ($itemIds as $singleItemId) {
                    // Handle each item
                    $this->saveItemRequest($buyerCookie, $dealerCode, $partnerIdentity, $singleItemId, $quantityNeeded);
                }
            }

            // Create a new punchout session
            $session = $this->sessionFactory->create();
            $session->setData(SessionInterface::BUYER_COOKIE, $buyerCookie);
            $session->setData(SessionInterface::CLIENT_TYPE, 'default');
            $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_NEW);
            $session->setData(SessionInterface::PARTNER_IDENTITY, $partnerIdentity);
            $session->setData(SessionInterface::CORP_ADDRESS_ID, $dealerCode);
            $this->sessionResource->save($session);

            // Get redirect URL from partner settings
            $redirectUrl = $this->getRedirectUrl($partnerIdentity);

            if (empty($redirectUrl)) {
                // If no redirect URL is configured, return success response
                $result = $this->rawFactory->create();
                $result->setHttpResponseCode(200);
                $result->setContents(json_encode(['success' => true, 'buyerCookie' => $buyerCookie]));
                $result->setHeader('Content-Type', 'application/json');
                return $result;
            }

            // Add cookie parameter to redirect URL
            $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
            $redirectUrl .= $separator . 'cookie=' . $buyerCookie;

            // Redirect to partner URL
            $resultRedirect = $this->redirectFactory->create();
            return $resultRedirect->setUrl($redirectUrl);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing item request: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(400);
            $result->setContents(json_encode(['error' => $e->getMessage()]));
            $result->setHeader('Content-Type', 'application/json');
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error processing item request: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(500);
            $result->setContents(json_encode(['error' => 'Internal server error']));
            $result->setHeader('Content-Type', 'application/json');
            return $result;
        }
    }

    /**
     * Save item request to database
     *
     * @param string $token
     * @param string $dealerCode
     * @param string $partnerIdentity
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    private function saveItemRequest(
        string $token,
        string $dealerCode,
        string $partnerIdentity,
        string $itemId,
        int $quantity
    ): void {
        try {
            // Create new item record
            $itemModel = $this->itemFactory->create();
            $itemModel->setData([
                'token' => $token,
                'dealer_code' => $dealerCode,
                'partner_identity' => $partnerIdentity,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'status' => 'pending'
            ]);
            $itemModel->save();

            $this->logger->info(
                'Punchout: Item request saved',
                [
                    'token' => $token,
                    'itemId' => $itemId,
                    'dealerCode' => $dealerCode
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error saving item request: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get redirect URL from partner settings
     *
     * @param string $partnerIdentity
     * @return string|null
     */
    private function getRedirectUrl(string $partnerIdentity): ?string
    {
        $punchoutPartners = $this->getPunchoutPartnersManagement->getResult();

        foreach ($punchoutPartners as $partner) {
            if (strtolower($partner['identity'] ?? '') === strtolower($partnerIdentity)) {
                return $partner['punchoutRedirectUrl'] ?? null;
            }
        }

        return null;
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

            // If in debug mode and cXML request is available, save it
            if ($this->config->isDebugMode() && isset($parsedData['cxml_request'])) {
                $session->setData('cxml_request', $parsedData['cxml_request']);
                $this->sessionResource->save($session);
            }

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
                throw new LocalizedException(__('Missing required cookie parameter'));
            }

            // Load session by buyer cookie
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid session'));
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

                // Add requested items to cart if any
                $itemsAdded = $this->addItemsToCart($buyerCookie);

                // Redirect to cart page if items were added, otherwise to home page
                $result = $this->redirectFactory->create();
                $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
                $result->setHeader('Pragma', 'no-cache', true);

                if ($itemsAdded) {
                    return $result->setPath('checkout/cart');
                } else {
                    return $result->setPath('customer/account');
                }
            } else {
                // No customer ID, redirect to login
                $result = $this->redirectFactory->create();
                return $result->setPath('customer/account');
            }
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error during shopping start: ' . $e->getMessage());
            $result = $this->redirectFactory->create();
            return $result->setPath('customer/account');
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error during shopping start: ' . $e->getMessage());
            $result = $this->redirectFactory->create();
            return $result->setPath('customer/account');
        }
    }

    /**
     * Load items by buyer cookie
     *
     * @param string $buyerCookie
     * @return array
     */
    private function loadItemsByBuyerCookie(string $buyerCookie): array
    {
        try {
            $collection = $this->itemCollectionFactory->create();
            $collection->addFieldToFilter('token', $buyerCookie);
            $collection->addFieldToFilter('status', 'pending');

            return $collection->getItems();
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error loading items by buyer cookie: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Add requested items to cart
     *
     * @param string $buyerCookie
     * @return bool
     */
    private function addItemsToCart(string $buyerCookie): bool
    {
        try {
            $items = $this->loadItemsByBuyerCookie($buyerCookie);

            if (empty($items)) {
                return false;
            }

            $itemsAdded = false;

            foreach ($items as $item) {
                try {
                    $productId = $this->getProductIdByItemId($item->getData('item_id'));

                    if (!$productId) {
                        $this->logger->warning('Punchout: Product not found for item: ' . $item->getData('item_id'));
                        continue;
                    }

                    $quantity = (int)$item->getData('quantity');
                    if ($quantity < 1) {
                        $quantity = 1;
                    }

                    // Add product to cart
                    $result = $this->cart->addProduct($productId, [
                        'qty' => $quantity
                    ]);

                    if ($result) {
                        // Update item status to added
                        $item->setData('status', 'added');
                        $this->itemResource->save($item);
                        $itemsAdded = true;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Punchout: Error adding item to cart: ' . $e->getMessage());
                    continue;
                }
            }

            if ($itemsAdded) {
                // Save cart
                $this->cart->save();
            }

            return $itemsAdded;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error processing items for cart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get product ID by item ID (SKU)
     *
     * @param string $itemId
     * @return int|null
     */
    private function getProductIdByItemId(string $itemId): ?int
    {
        try {
            $product = $this->productRepository->get($itemId);
            return (int)$product->getId();
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error loading product by SKU: ' . $e->getMessage());
            return null;
        }
    }

    public function processPortalAddressSubmit(RequestInterface $request)
    {
        $buyerCookie = $request->getParam('cookie');
        $addressId = $request->getParam('locationId');

        try {
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
                'cookie' => $buyerCookie
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
