<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model;

use Magento\Framework\Model\AbstractModel;

class LocationRelation extends AbstractModel
{
    const CACHE_TAG = 'tirehub_transfernetwork_location_relation';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'tirehub_transfernetwork_location_relation';

    protected function _construct()
    {
        $this->_init(\Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getRelationId(): ?int
    {
        return $this->getData('relation_id') ? (int)$this->getData('relation_id') : null;
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

    public function getColorId(): ?int
    {
        return $this->getData('color_id') ? (int)$this->getData('color_id') : null;
    }

    public function setColorId(?int $colorId): self
    {
        return $this->setData('color_id', $colorId);
    }

    public function getActive(): bool
    {
        return (bool)$this->getData('active');
    }

    public function setActive(bool $active): self
    {
        return $this->setData('active', $active);
    }

    public function getCutoffDays(): ?float
    {
        return $this->getData('cutoff_days') ? (float)$this->getData('cutoff_days') : null;
    }

    public function setCutoffDays(?float $cutoffDays): self
    {
        return $this->setData('cutoff_days', $cutoffDays);
    }

    public function getCutoffTime(): ?string
    {
        return $this->getData('cutoff_time');
    }

    public function setCutoffTime(?string $cutoffTime): self
    {
        if ($cutoffTime) {
            if (preg_match('/^\d{2}:\d{2}$/', $cutoffTime)) {
                $cutoffTime = $cutoffTime . ':00';
            } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
            } else {
                try {
                    $time = new \DateTime($cutoffTime);
                    $cutoffTime = $time->format('H:i:s');
                } catch (\Exception $e) {
                    $cutoffTime = null;
                }
            }
        }
        return $this->setData('cutoff_time', $cutoffTime);
    }

    public function getUnloadMinutes(): ?int
    {
        return $this->getData('unload_minutes') ? (int)$this->getData('unload_minutes') : null;
    }

    public function setUnloadMinutes(?int $unloadMinutes): self
    {
        return $this->setData('unload_minutes', $unloadMinutes);
    }

    public function getFormattedCutoffTime(): string
    {
        $time = $this->getCutoffTime();
        if (!$time) {
            return '';
        }

        try {
            $dateTime = new \DateTime($time);
            return $dateTime->format('g:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }
}
