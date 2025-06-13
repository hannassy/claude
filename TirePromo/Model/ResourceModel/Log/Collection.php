<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\TirePromo\Model\Log;
use Tirehub\TirePromo\Model\ResourceModel\Log as ResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'tirepromo_log_collection';
    protected $_eventObject = 'tirepromo_log_collection';

    protected function _construct()
    {
        $this->_init(Log::class, ResourceModel::class);
    }
}
