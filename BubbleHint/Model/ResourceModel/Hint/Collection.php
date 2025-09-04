<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model\ResourceModel\Hint;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\BubbleHint\Model\Hint;
use Tirehub\BubbleHint\Model\ResourceModel\Hint as ResourceModelHint;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'hint_id';

    protected function _construct(): void
    {
        $this->_init(
            Hint::class,
            ResourceModelHint::class
        );
    }
}
