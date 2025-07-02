<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\ResourceModel\Location;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'location_id';
    protected $_eventPrefix = 'tirehub_transfernetwork_location_collection';
    protected $_eventObject = 'location_collection';

    protected function _construct()
    {
        $this->_init(
            \Tirehub\TransferNetwork\Model\Location::class,
            \Tirehub\TransferNetwork\Model\ResourceModel\Location::class
        );
    }
}