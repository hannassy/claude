<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tirepromo_log', 'entity_id');
    }
}
