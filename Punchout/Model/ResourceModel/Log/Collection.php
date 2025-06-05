<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\Punchout\Model\Log;
use Tirehub\Punchout\Model\ResourceModel\Log as ResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected $_eventPrefix = 'punchout_log_collection';

    protected function _construct()
    {
        $this->_init(Log::class, ResourceModel::class);
    }
}
