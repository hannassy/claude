<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Api;

interface PromoCodeStorageInterface
{
    public function savePromoCode(array $promoData, int $quoteId): void;

    public function getPromoCode(int $quoteId): ?string;

    public function isOrderLevel(int $quoteId): bool;

    public function isNationWide(int $quoteId): bool;

    public function getAppliedSkus(int $quoteId): ?string;

    public function getDiscountPercent(int $quoteId): ?float;

    public function getDiscountAmount(int $quoteId): ?float;

    public function removePromoCode(int $quoteId): void;

    public function checkLineItemLevel(int $quoteId): bool;
}
