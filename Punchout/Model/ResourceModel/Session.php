<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Session extends AbstractDb
{
    const STATUS_NEW = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_ERROR = 3;

    protected function _construct()
    {
        $this->_init('tirehub_punchout_session', 'entity_id');
    }
}
