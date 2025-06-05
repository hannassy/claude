<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Tirehub\Catalog\Api\RenderRegionalProductsInterface;
use Tirehub\Checkout\Service\Management\LookupInventoryManagement;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Api\EnablePunchoutModeInterface;
use Tirehub\Punchout\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Tirehub\Punchout\Model\ResourceModel\Item as ItemResource;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\Session\SessionManagerInterface;
use Tirehub\Punchout\Model\LogFactory;

class ShoppingStart
{
    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly SessionResource $sessionResource,
        private readonly CustomerSession $customerSession,
        private readonly EnablePunchoutModeInterface $enablePunchoutMode,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly ItemResource $itemResource,
        private readonly Cart $cart,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LookupInventoryManagement $lookupInventoryManagement,
        private readonly RenderRegionalProductsInterface $renderRegionalProducts,
        private readonly Monolog $logger,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly HttpContext $httpContext,
        private readonly SessionManagerInterface $session,
        private readonly LogFactory $logFactory
    ) {
    }

    public function execute(RequestInterface $request): bool
    {
        $buyerCookie = null;
        $log = $this->logFactory->create();

        try {
            $buyerCookie = $request->getParam('cookie');

            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing required cookie parameter'));
            }

            $log->logInfo('Starting shopping session', [
                'buyerCookie' => $buyerCookie
            ], $buyerCookie);

            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid session'));
            }

            $customerId = $session->getData(SessionInterface::CUSTOMER_ID);

            if ($customerId) {
                // Clear any existing login state if customer ID is different
                if ($this->customerSession->isLoggedIn() && $this->customerSession->getCustomerId() != $customerId) {
                    $this->logger->info('Punchout: Logging out existing customer before punchout login');

                    $log->logInfo('Logging out existing customer', [
                        'existing_customer_id' => $this->customerSession->getCustomerId(),
                        'new_customer_id' => $customerId
                    ], $buyerCookie);

                    // Force logout and clear all session data
                    $this->customerSession->logout();
                    $this->customerSession->regenerateId();
                    $this->customerSession->clearStorage();

                    // Clear session cookies
                    $metadata = $this->cookieMetadataFactory
                        ->createPublicCookieMetadata()
                        ->setDuration(0)
                        ->setPath('/')
                        ->setHttpOnly(false);

                    $this->cookieManager->deleteCookie('private_content_version', $metadata);
                    $this->cookieManager->deleteCookie('section_data_ids', $metadata);
                    $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);

                    // Force clear HTTP context
                    $this->httpContext->setValue(CustomerContext::CONTEXT_AUTH, false, false);
                    $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, 0, 0);

                    // Small delay to ensure logout is processed
                    usleep(500000); // 0.5 second
                }

                $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_ACTIVE);
                $this->sessionResource->save($session);

                // Log in the customer if not already logged in
                if (!$this->customerSession->isLoggedIn()) {
                    $this->customerSession->loginById($customerId);
                    $this->customerSession->regenerateId();

                    // Force new private content version
                    $this->customerSession->setData('private_content_version', time());

                    // Update HTTP context
                    $customer = $this->customerRepository->getById($customerId);
                    $this->httpContext->setValue(CustomerContext::CONTEXT_AUTH, true, false);
                    $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, $customer->getGroupId(), 0);

                    $log->logInfo('Customer logged in successfully', [
                        'customer_id' => $customerId,
                        'customer_email' => $customer->getEmail()
                    ], $buyerCookie);
                }

                // Enable punchout mode
                $this->enablePunchoutMode->execute($buyerCookie);

                $this->logger->info('Punchout: Customer logged in: ' . $customerId);

                $this->clearCustomerCart();

                $log->logInfo('Cart cleared', [], $buyerCookie);

                // Add requested items to cart if any
                $hasItems = $this->addItemsToCart($buyerCookie);

                return $hasItems;
            }

            return false;
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error during shopping start: ' . $e->getMessage());

            $log->logError('Error during shopping start', [
                'error' => $e->getMessage()
            ], $buyerCookie);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error during shopping start: ' . $e->getMessage());

            $log->logCritical('Unexpected error during shopping start', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], $buyerCookie);

            throw $e;
        }
    }

    private function clearCustomerCart(): void
    {
        try {
            $quote = $this->cart->getQuote();

            foreach ($quote->getAllItems() as $item) {
                $quote->removeItem($item->getId());
            }

            $quote->collectTotals()->save();

            $this->cart->truncate()->save();
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error clearing cart: ' . $e->getMessage());
        }
    }

    private function addItemsToCart(string $buyerCookie): bool
    {
        $log = $this->logFactory->create();

        try {
            $items = $this->loadItemsByBuyerCookie($buyerCookie);

            if (empty($items)) {
                $log->logInfo('No items to add to cart', [], $buyerCookie);
                return false;
            }

            $log->logInfo('Found items to add to cart', [
                'items_count' => count($items)
            ], $buyerCookie);

            $itemsAdded = false;
            $failedItems = [];

            foreach ($items as $item) {
                try {
                    $result = $this->addProductToCart($item);

                    if ($result) {
                        $item->setData('status', 'added');
                        $this->itemResource->save($item);
                        $itemsAdded = true;

                        $log->logInfo('Item added to cart', [
                            'item_id' => $item->getData('item_id'),
                            'quantity' => $item->getData('quantity')
                        ], $buyerCookie);
                    } else {
                        $failedItems[] = $item->getData('item_id');

                        $log->logWarning('Failed to add item to cart', [
                            'item_id' => $item->getData('item_id')
                        ], $buyerCookie);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Punchout: Error adding item to cart: ' . $e->getMessage());
                    $failedItems[] = $item->getData('item_id');

                    $log->logError('Error adding item to cart', [
                        'item_id' => $item->getData('item_id'),
                        'error' => $e->getMessage()
                    ], $buyerCookie);

                    continue;
                }
            }

            if (!empty($failedItems)) {
                $this->storeFailedItemsMessage($failedItems);

                $log->logWarning('Some items failed to add to cart', [
                    'failed_items' => $failedItems
                ], $buyerCookie);
            }

            return $itemsAdded;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error processing items for cart: ' . $e->getMessage());

            $log->logError('Error processing items for cart', [
                'error' => $e->getMessage()
            ], $buyerCookie);

            return false;
        }
    }

    private function storeFailedItemsMessage(array $failedItems): void
    {
        $message = __('The following items could not be added to cart: %1', implode(', ', $failedItems));

        $messages = $this->session->getData('punchout_deferred_messages') ?: [];
        $messages[] = [
            'type' => 'error',
            'text' => $message->render()
        ];

        $this->session->setData('punchout_deferred_messages', $messages);
    }

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

    private function addProductToCart(DataObject $item): bool
    {
        try {
            $sku = $item->getData('item_id');
            $productId = $this->getProductIdByItemId($sku);

            if (!$productId) {
                $this->logger->warning('Punchout: Product not found for item: ' . $sku);
                return false;
            }

            $totalQty = max((int)$item->getData('quantity'), 1);

            $params = [
                'itemId' => $sku,
                'quantityNeeded' => 1,
                'searchQuantityNeeded' => $totalQty
            ];

            $locations = $this->lookupInventoryManagement->getResult($params);
            $locations = $this->renderRegionalProducts->execute($locations['results'] ?? [], $params);

            if (empty($locations)) {
                $this->logger->warning('Punchout: No inventory locations found for item: ' . $sku);
                return false;
            }

            $processedLocations = $this->distributeQuantityAcrossLocations($locations, $totalQty);

            $this->removeExistingItemsFromCart($sku);

            foreach ($processedLocations as $locationData) {
                if (!isset($locationData['itemId']) || empty($locationData['qty'])) {
                    continue;
                }

                $product = $this->productRepository->get($locationData['itemId']);
                $this->addProductWithLocationData($product, $locationData);
            }

            $this->updateCartTotals();

            $this->logger->info('Punchout: Successfully added product to cart: ' . $sku);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error adding product to cart: ' . $e->getMessage());
            return false;
        }
    }

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

    private function distributeQuantityAcrossLocations(array $locations, int $totalQty): array
    {
        $processedLocations = [];
        $remainingQty = $totalQty;

        foreach ($locations as $location) {
            $qtyAvailable = (int)($location['quantityAvailable'] ?? 0);

            if ($qtyAvailable <= 0) {
                continue;
            }

            $locationData = $location;
            $locationData['qty'] = min($qtyAvailable, $remainingQty);
            $locationData['location_type'] = $location['locationType'] ?? '';
            $locationData['location_code'] = $location['locationId'] ?? '';

            $processedLocations[] = $locationData;

            $remainingQty -= $locationData['qty'];

            if ($remainingQty <= 0) {
                break;
            }
        }

        return $processedLocations;
    }

    private function addProductWithLocationData($product, array $locationData): void
    {
        $requestData = $this->prepareLocationRequestData($locationData);
        $this->cart->addProduct($product, $requestData);
    }

    private function prepareLocationRequestData(array $locationData): array
    {
        $cleanData = $locationData;
        unset($cleanData['pricingDetails']);
        unset($cleanData['address']);
        unset($cleanData['deliveryInfo']);

        $cleanData['force'] = true;

        $requestParams = [
            'qty' => isset($cleanData['qty']) ? ($cleanData['qty'] ?: 1) : 1,
            'request' => $cleanData,
            'super_group' => $cleanData['super_group'] ?? null,
            'bundle_option' => $cleanData['bundle_option'] ?? null,
            'location_code' => $cleanData['location_code'] ?? '',
        ];

        return array_merge($cleanData, $requestParams);
    }

    private function updateCartTotals(): void
    {
        $this->cart->getQuote()->setTotalsCollectedFlag(false);
        $this->cart->getQuote()->collectTotals();
        $this->cart->save();
    }

    private function removeExistingItemsFromCart(string $sku): void
    {
        try {
            $quote = $this->cart->getQuote();
            $itemsToRemove = [];

            foreach ($quote->getAllItems() as $item) {
                if ($item->getSku() === $sku) {
                    $itemsToRemove[] = $item->getItemId();
                }
            }

            foreach ($itemsToRemove as $itemId) {
                $this->cart->removeItem($itemId);
            }

            if (!empty($itemsToRemove)) {
                $this->logger->info('Punchout: Removed ' . count($itemsToRemove) . ' existing items with SKU: ' . $sku);
                $this->cart->save();
            }
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error removing existing items from cart: ' . $e->getMessage());
        }
    }
}
