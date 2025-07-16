<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\ResourceModel\Color;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'tirehub_transfernetwork_color_collection';
    protected $_eventObject = 'color_collection';

    protected function _construct()
    {
        $this->_init(
            \Tirehub\TransferNetwork\Model\Color::class,
            \Tirehub\TransferNetwork\Model\ResourceModel\Color::class
        );
    }
}
