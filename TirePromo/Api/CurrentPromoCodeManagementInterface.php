<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Api;

use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\Quote;
use Tirehub\TirePromo\Exception\InvalidPromoCodePatternException;
use Tirehub\TirePromo\Exception\LookupPromoCodeException;

interface CurrentPromoCodeManagementInterface
{
    /**
     * @throws LookupPromoCodeException
     * @throws NotFoundException
     * @throws InvalidPromoCodePatternException
     */
    public function savePromoCode(string $promoCode): bool;

    public function getPromoCode(?Quote $quote = null): ?string;

    public function isOrderLevel(?Quote $quote = null): bool;

    public function isNationWide(?Quote $quote = null): bool;

    public function getAppliedSkus(?Quote $quote = null): ?string;

    public function getDiscountPercent(?Quote $quote = null): ?float;

    public function checkLineItemLevel(?Quote $quote = null): bool;

    public function getDiscountAmount(?Quote $quote = null): ?float;

    public function getOrderSubtotal(): ?float;

    public function getOrderTaxAmount(): ?float;

    public function getOrderShippingAmount(): ?float;

    public function getOrderSpecialAmount(): ?float;

    public function removePromoCode(): void;
}
