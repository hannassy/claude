<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\TirePromo\Api\Data\LogInterface;
use Tirehub\TirePromo\Model\ResourceModel\Log as ResourceModel;

class Log extends AbstractModel implements LogInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    public function getPromoCode(): string
    {
        return (string)$this->getData(self::PROMO_CODE);
    }

    public function setPromoCode(string $promoCode): self
    {
        return $this->setData(self::PROMO_CODE, $promoCode);
    }

    public function getShipTo(): ?string
    {
        return $this->getData(self::SHIP_TO);
    }

    public function setShipTo(?string $shipTo): self
    {
        return $this->setData(self::SHIP_TO, $shipTo);
    }

    public function getSku(): ?string
    {
        return $this->getData(self::SKU);
    }

    public function setSku(?string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getBrandPattern(): ?string
    {
        return $this->getData(self::BRAND_PATTERN);
    }

    public function setBrandPattern(?string $brandPattern): self
    {
        return $this->setData(self::BRAND_PATTERN, $brandPattern);
    }

    public function getQty(): int
    {
        return (int)$this->getData(self::QTY);
    }

    public function setQty(int $qty): self
    {
        return $this->setData(self::QTY, $qty);
    }

    public function getDiscountTotal(): float
    {
        return (float)$this->getData(self::DISCOUNT_TOTAL);
    }

    public function setDiscountTotal(float $discountTotal): self
    {
        return $this->setData(self::DISCOUNT_TOTAL, $discountTotal);
    }

    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) ? (int)$this->getData(self::ORDER_ID) : null;
    }

    public function setOrderId(?int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getQuoteId(): ?int
    {
        return $this->getData(self::QUOTE_ID) ? (int)$this->getData(self::QUOTE_ID) : null;
    }

    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getCustomerId(): ?int
    {
        return $this->getData(self::CUSTOMER_ID) ? (int)$this->getData(self::CUSTOMER_ID) : null;
    }

    public function setCustomerId(?int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
