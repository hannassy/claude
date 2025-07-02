<?php
/**
 * Path: app/code/Tirehub/TransferNetwork/Model/ResourceModel/Location.php
 */

namespace Tirehub\TransferNetwork\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Location extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tirehub_transfernetwork_location', 'location_id');
    }
}