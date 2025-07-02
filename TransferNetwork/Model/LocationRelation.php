<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class LocationRelation extends AbstractModel implements IdentityInterface
{
    const CACHE_TAG = 'tirehub_transfernetwork_location_relation';

    protected $_cacheTag = 'tirehub_transfernetwork_location_relation';
    protected $_eventPrefix = 'tirehub_transfernetwork_location_relation';

    protected function _construct()
    {
        $this->_init(\Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        return [
            'active' => 1
        ];
    }

    public function getRelationId(): ?int
    {
        return $this->getData('relation_id') ? (int)$this->getData('relation_id') : null;
    }

    public function setRelationId(int $relationId): self
    {
        return $this->setData('relation_id', $relationId);
    }

    public function getLocationIdFrom(): ?int
    {
        return $this->getData('location_id_from') ? (int)$this->getData('location_id_from') : null;
    }

    public function setLocationIdFrom(int $locationIdFrom): self
    {
        return $this->setData('location_id_from', $locationIdFrom);
    }

    public function getLocationIdTo(): ?int
    {
        return $this->getData('location_id_to') ? (int)$this->getData('location_id_to') : null;
    }

    public function setLocationIdTo(int $locationIdTo): self
    {
        return $this->setData('location_id_to', $locationIdTo);
    }

    public function getActive(): bool
    {
        return (bool)$this->getData('active');
    }

    public function setActive(bool $active): self
    {
        return $this->setData('active', $active);
    }

    public function getCutoffDays(): ?int
    {
        return $this->getData('cutoff_days') ? (int)$this->getData('cutoff_days') : null;
    }

    public function setCutoffDays(?int $cutoffDays): self
    {
        return $this->setData('cutoff_days', $cutoffDays);
    }
}