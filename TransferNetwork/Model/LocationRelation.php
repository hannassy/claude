<?php
/**
 * Path: app/code/Tirehub/TransferNetwork/Model/LocationRelation.php
 */

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
        $values = [];
        return $values;
    }

    /**
     * Get Relation ID
     * @return int|null
     */
    public function getRelationId()
    {
        return $this->getData('relation_id');
    }

    /**
     * Set Relation ID
     * @param int $relationId
     * @return $this
     */
    public function setRelationId($relationId)
    {
        return $this->setData('relation_id', $relationId);
    }

    /**
     * Get Location ID From
     * @return int|null
     */
    public function getLocationIdFrom()
    {
        return $this->getData('location_id_from');
    }

    /**
     * Set Location ID From
     * @param int $locationIdFrom
     * @return $this
     */
    public function setLocationIdFrom($locationIdFrom)
    {
        return $this->setData('location_id_from', $locationIdFrom);
    }

    /**
     * Get Location ID To
     * @return int|null
     */
    public function getLocationIdTo()
    {
        return $this->getData('location_id_to');
    }

    /**
     * Set Location ID To
     * @param int $locationIdTo
     * @return $this
     */
    public function setLocationIdTo($locationIdTo)
    {
        return $this->setData('location_id_to', $locationIdTo);
    }
}