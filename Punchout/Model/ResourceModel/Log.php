<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tirehub_punchout_log', 'entity_id');
    }
}
