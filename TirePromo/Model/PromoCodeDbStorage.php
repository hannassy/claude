<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model;

use Tirehub\CssPriceAdjustment\Api\CssPriceCartRepositoryInterface;
use Tirehub\TirePromo\Api\PromoCodeStorageInterface;

class PromoCodeDbStorage implements PromoCodeStorageInterface
{
    public function __construct(
        private readonly CssPriceCartRepositoryInterface $cssPriceCartRepository
    ) {
    }

    public function savePromoCode(array $promoData, int $quoteId): void
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);
        $calculatedAppliedSkus = $this->getCalculatedAppliedSkus($promoData);

        foreach ($cssPriceCart->getItems() as $cssPriceCartItem) {
            $cssPriceCartItem->setQuoteId($quoteId);
            $cssPriceCartItem->setPromoCode($promoData['promoCode'] ?? null);

            $orderLevelDiscount = $promoData['orderLevelDiscount'] ?? 0;
            if ($orderLevelDiscount) {
                $cssPriceCartItem->setDiscountAmount($orderLevelDiscount);
            }

            $orderLevelDiscountPercent = $promoData['orderLevelDiscountPercent'] ?? 0;
            if ($orderLevelDiscountPercent) {
                $cssPriceCartItem->setDiscountPercent($orderLevelDiscountPercent);
            }

            $cssPriceCartItem->setPromoDescription($promoData['promoDescription'] ?? null);
            $cssPriceCartItem->setNationWide($promoData['promoInfo']['nationwide'] ?? null);
            $cssPriceCartItem->setOrderLevel($promoData['promoInfo']['ordelLevel'] ?? null);
            $cssPriceCartItem->setAppliedSkus($calculatedAppliedSkus);

            $this->cssPriceCartRepository->save($cssPriceCartItem);
        }
    }

    public function getPromoCode(int $quoteId): ?string
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();
        if (empty($items)) {
            return null;
        }

        $firstItem = reset($items);

        return $firstItem->getPromoCode();
    }

    public function isOrderLevel(int $quoteId): bool
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();

        if (empty($items)) {
            return false;
        }

        $firstItem = reset($items);

        return (bool)$firstItem->getOrderLevel();
    }

    public function isNationWide(int $quoteId): bool
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();

        if (empty($items)) {
            return false;
        }

        $firstItem = reset($items);

        return (bool)$firstItem->getNationWide();
    }

    public function getAppliedSkus(int $quoteId): string
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();

        if (empty($items)) {
            return '';
        }

        $firstItem = reset($items);

        return (string)$firstItem->getAppliedSkus();
    }

    public function getDiscountAmount(int $quoteId): ?float
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();
        if (empty($items)) {
            return null;
        }

        $firstItem = reset($items);

        return $firstItem->getDiscountAmount();
    }

    public function getDiscountPercent(int $quoteId): ?float
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);
        $items = $cssPriceCart->getItems();
        if (empty($items)) {
            return null;
        }

        $firstItem = reset($items);

        return $firstItem->getDiscountPercent();
    }

    public function removePromoCode(int $quoteId): void
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        foreach ($cssPriceCart->getItems() as $cssPriceCartItem) {
            $this->cssPriceCartRepository->delete($cssPriceCartItem);
        }
    }

    public function checkLineItemLevel(int $quoteId): bool
    {
        $cssPriceCart = $this->cssPriceCartRepository->getByCartId($quoteId);

        $items = $cssPriceCart->getItems();

        if (empty($items)) {
            return false;
        }

        $firstItem = reset($items);

        return (bool)$firstItem->getItemId();
    }

    private function getCalculatedAppliedSkus(array $promoData): string
    {
        $appliedSkus = [];
        $cartItems = $promoData['cartItems'] ?? [];
        foreach ($cartItems as $cartItem) {
            $lineItemDiscount = $cartItem['lineItemDiscount'] ?? 0;
            if (!$lineItemDiscount) {
                continue;
            }

            $appliedSkus[] = $cartItem['itemId'] ?? '';
        }

        return implode(',', $appliedSkus);
    }
}
