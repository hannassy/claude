<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class Location extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'tirehub_transfernetwork_location';

    protected $_cacheTag = 'tirehub_transfernetwork_location';

    protected $_eventPrefix = 'tirehub_transfernetwork_location';

    protected function _construct()
    {
        $this->_init(\Tirehub\TransferNetwork\Model\ResourceModel\Location::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        return [
            'active' => 1,
            'rdc_inventory_visible' => 0
        ];
    }

    public function getLocationId(): ?int
    {
        return $this->getData('location_id') ? (int)$this->getData('location_id') : null;
    }

    public function setLocationId(int $locationId): self
    {
        return $this->setData('location_id', $locationId);
    }

    public function getLocationName(): ?string
    {
        return $this->getData('location_name');
    }

    public function setLocationName(string $locationName): self
    {
        return $this->setData('location_name', $locationName);
    }

    public function getLatitude(): ?float
    {
        return $this->getData('latitude') ? (float)$this->getData('latitude') : null;
    }

    public function setLatitude(float $latitude): self
    {
        return $this->setData('latitude', $latitude);
    }

    public function getLongitude(): ?float
    {
        return $this->getData('longitude') ? (float)$this->getData('longitude') : null;
    }

    public function setLongitude(float $longitude): self
    {
        return $this->setData('longitude', $longitude);
    }

    public function getRdcCluster(): ?string
    {
        return $this->getData('rdc_cluster');
    }

    public function setRdcCluster(?string $rdcCluster): self
    {
        return $this->setData('rdc_cluster', $rdcCluster);
    }

    public function getPinColor(): ?string
    {
        return $this->getData('pin_color');
    }

    public function setPinColor(?string $pinColor): self
    {
        return $this->setData('pin_color', $pinColor);
    }

    public function getActive(): bool
    {
        return (bool)$this->getData('active');
    }

    public function setActive(bool $active): self
    {
        return $this->setData('active', $active);
    }

    public function getRdcInventoryVisible(): bool
    {
        return (bool)$this->getData('rdc_inventory_visible');
    }

    public function setRdcInventoryVisible(bool $rdcInventoryVisible): self
    {
        return $this->setData('rdc_inventory_visible', $rdcInventoryVisible);
    }
}