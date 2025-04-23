<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\Punchout\Model\Item;
use Tirehub\Punchout\Model\ResourceModel\Item as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'punchout_item_collection';

    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(Item::class, ResourceModel::class);
    }
}
