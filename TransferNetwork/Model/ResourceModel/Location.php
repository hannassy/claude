<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Location extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tirehub_transfernetwork_location', 'entity_id');
    }
}