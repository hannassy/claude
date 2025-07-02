<?php
/**
 * Path: app/code/Tirehub/TransferNetwork/Model/ResourceModel/LocationRelation/Collection.php
 */

namespace Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'relation_id';
    protected $_eventPrefix = 'tirehub_transfernetwork_location_relation_collection';
    protected $_eventObject = 'location_relation_collection';

    protected function _construct()
    {
        $this->_init(
            \Tirehub\TransferNetwork\Model\LocationRelation::class,
            \Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation::class
        );
    }

    /**
     * Join location details for from and to locations
     * @return $this
     */
    public function joinLocationDetails()
    {
        $this->getSelect()
            ->joinLeft(
                ['location_from' => $this->getTable('tirehub_transfernetwork_location')],
                'main_table.location_id_from = location_from.location_id',
                [
                    'from_location_name' => 'location_from.location_name',
                    'from_latitude' => 'location_from.latitude',
                    'from_longitude' => 'location_from.longitude'
                ]
            )
            ->joinLeft(
                ['location_to' => $this->getTable('tirehub_transfernetwork_location')],
                'main_table.location_id_to = location_to.location_id',
                [
                    'to_location_name' => 'location_to.location_name',
                    'to_latitude' => 'location_to.latitude',
                    'to_longitude' => 'location_to.longitude'
                ]
            );

        return $this;
    }
}