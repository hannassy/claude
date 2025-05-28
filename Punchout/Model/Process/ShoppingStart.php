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
        private readonly Monolog $logger
    ) {
    }

    public function execute(RequestInterface $request): bool
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
                // Make sure we're starting with a clean session
                if ($this->customerSession->isLoggedIn()) {
                    $this->customerSession->logout();
                    $this->customerSession->regenerateId();
                }

                $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_ACTIVE);
                $this->sessionResource->save($session);

                // Log in the customer
                $this->customerSession->loginById($customerId);

                // Enable punchout mode
                $this->enablePunchoutMode->execute($buyerCookie);

                $this->logger->info('Punchout: Customer logged in: ' . $customerId);

                $this->clearCustomerCart();

                // Add requested items to cart if any
                return $this->addItemsToCart($buyerCookie);
            }

            return false;
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error during shopping start: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error during shopping start: ' . $e->getMessage());
            throw $e;
        }
    }

    private function clearCustomerCart(): void
    {
        try {
            $this->cart->truncate()->save();
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error during shopping start: ' . $e->getMessage());
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
                    $result = $this->addProductToCart($item);

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

            return $itemsAdded;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error processing items for cart: ' . $e->getMessage());
            return false;
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
     * Add requested product to cart with proper location information
     *
     * @param DataObject $item Item to add to cart
     * @return bool Success status
     */
    private function addProductToCart(DataObject $item): bool
    {
        try {
            // Get product ID from item SKU
            $sku = $item->getData('item_id');
            $productId = $this->getProductIdByItemId($sku);

            if (!$productId) {
                $this->logger->warning('Punchout: Product not found for item: ' . $sku);
                return false;
            }

            // Determine quantity needed
            $totalQty = max((int)$item->getData('quantity'), 1);

            // Prepare location lookup parameters
            $params = [
                'itemId' => $sku,
                'quantityNeeded' => 1,
                'searchQuantityNeeded' => $totalQty
            ];

            // Get available locations with inventory
            $locations = $this->lookupInventoryManagement->getResult($params);
            $locations = $this->renderRegionalProducts->execute($locations['results'] ?? [], $params);

            if (empty($locations)) {
                $this->logger->warning('Punchout: No inventory locations found for item: ' . $sku);
                return false;
            }

            // Process inventory from locations
            $processedLocations = $this->distributeQuantityAcrossLocations($locations, $totalQty);

            // Remove existing items with same SKU from cart before adding new ones
            $this->removeExistingItemsFromCart($sku);

            // Add products from each location
            foreach ($processedLocations as $locationData) {
                if (!isset($locationData['itemId']) || empty($locationData['qty'])) {
                    continue;
                }

                $product = $this->productRepository->get($locationData['itemId']);
                $this->addProductWithLocationData($product, $locationData);
            }

            // Update cart totals
            $this->updateCartTotals();

            $this->logger->info('Punchout: Successfully added product to cart: ' . $sku);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error adding product to cart: ' . $e->getMessage());
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

    /**
     * Distribute requested quantity across available inventory locations
     *
     * @param array $locations Available inventory locations
     * @param int $totalQty Total quantity needed
     * @return array Processed location data with assigned quantities
     */
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

    /**
     * Add product to cart with location data
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product Product to add
     * @param array $locationData Location data with inventory
     * @return void
     * @throws LocalizedException
     */
    private function addProductWithLocationData($product, array $locationData): void
    {
        // Clean up unnecessary location data
        $requestData = $this->prepareLocationRequestData($locationData);

        // Add product to cart
        $this->cart->addProduct($product, $requestData);
    }

    /**
     * Prepare location request data for cart
     *
     * @param array $locationData Raw location data
     * @return array Cleaned location request data
     */
    private function prepareLocationRequestData(array $locationData): array
    {
        // Remove unnecessary data
        $cleanData = $locationData;
        unset($cleanData['pricingDetails']);
        unset($cleanData['address']);
        unset($cleanData['deliveryInfo']);

        // Set required flags
        $cleanData['force'] = true;

        // Set additional request parameters
        $requestParams = [
            'qty' => isset($cleanData['qty']) ? ($cleanData['qty'] ?: 1) : 1,
            'request' => $cleanData,
            'super_group' => $cleanData['super_group'] ?? null,
            'bundle_option' => $cleanData['bundle_option'] ?? null,
            'location_code' => $cleanData['location_code'] ?? '',
        ];

        return array_merge($cleanData, $requestParams);
    }

    /**
     * Update cart totals
     *
     * @return void
     */
    private function updateCartTotals(): void
    {
        $this->cart->getQuote()->setTotalsCollectedFlag(false);
        $this->cart->getQuote()->collectTotals();
        $this->cart->save();
    }

    /**
     * Remove existing items with the same SKU from cart
     *
     * @param string $sku
     * @return void
     */
    private function removeExistingItemsFromCart(string $sku): void
    {
        try {
            $quote = $this->cart->getQuote();
            $itemsToRemove = [];

            // Identify items with matching SKU
            foreach ($quote->getAllItems() as $item) {
                if ($item->getSku() === $sku) {
                    $itemsToRemove[] = $item->getItemId();
                }
            }

            // Remove identified items
            foreach ($itemsToRemove as $itemId) {
                $this->cart->removeItem($itemId);
            }

            if (!empty($itemsToRemove)) {
                $this->logger->info('Punchout: Removed ' . count($itemsToRemove) . ' existing items with SKU: ' . $sku);
                // Save cart after removing items
                $this->cart->save();
            }
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error removing existing items from cart: ' . $e->getMessage());
        }
    }
}
