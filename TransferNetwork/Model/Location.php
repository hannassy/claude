<?php
/**
 * Path: app/code/Tirehub/TransferNetwork/Model/Location.php
 */

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
        $values = [];
        return $values;
    }

    /**
     * Get Location ID
     * @return int|null
     */
    public function getLocationId()
    {
        return $this->getData('location_id');
    }

    /**
     * Set Location ID
     * @param int $locationId
     * @return $this
     */
    public function setLocationId($locationId)
    {
        return $this->setData('location_id', $locationId);
    }

    /**
     * Get Location Name
     * @return string|null
     */
    public function getLocationName()
    {
        return $this->getData('location_name');
    }

    /**
     * Set Location Name
     * @param string $locationName
     * @return $this
     */
    public function setLocationName($locationName)
    {
        return $this->setData('location_name', $locationName);
    }

    /**
     * Get Latitude
     * @return float|null
     */
    public function getLatitude()
    {
        return $this->getData('latitude');
    }

    /**
     * Set Latitude
     * @param float $latitude
     * @return $this
     */
    public function setLatitude($latitude)
    {
        return $this->setData('latitude', $latitude);
    }

    /**
     * Get Longitude
     * @return float|null
     */
    public function getLongitude()
    {
        return $this->getData('longitude');
    }

    /**
     * Set Longitude
     * @param float $longitude
     * @return $this
     */
    public function setLongitude($longitude)
    {
        return $this->setData('longitude', $longitude);
    }
}