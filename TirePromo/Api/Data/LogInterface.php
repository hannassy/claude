<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Api\Data;

interface LogInterface
{
    const ENTITY_ID = 'entity_id';
    const PROMO_CODE = 'promo_code';
    const SHIP_TO = 'ship_to';
    const SKU = 'sku';
    const BRAND_PATTERN = 'brand_pattern';
    const QTY = 'qty';
    const DISCOUNT_TOTAL = 'discount_total';
    const ORDER_ID = 'order_id';
    const QUOTE_ID = 'quote_id';
    const CUSTOMER_ID = 'customer_id';
    const CREATED_AT = 'created_at';

    public function getEntityId(): ?int;

    public function getPromoCode(): string;

    public function setPromoCode(string $promoCode): self;

    public function getShipTo(): ?string;

    public function setShipTo(?string $shipTo): self;

    public function getSku(): ?string;

    public function setSku(?string $sku): self;

    public function getBrandPattern(): ?string;

    public function setBrandPattern(?string $brandPattern): self;

    public function getQty(): int;

    public function setQty(int $qty): self;

    public function getDiscountTotal(): float;

    public function setDiscountTotal(float $discountTotal): self;

    public function getOrderId(): ?int;

    public function setOrderId(?int $orderId): self;

    public function getQuoteId(): ?int;

    public function setQuoteId(?int $quoteId): self;

    public function getCustomerId(): ?int;

    public function setCustomerId(?int $customerId): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(string $createdAt): self;
}
