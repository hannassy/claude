<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Item extends AbstractDb
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('tirehub_punchout_item', 'entity_id');
    }
}
