<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model\ResourceModel\HintSeen;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\BubbleHint\Api\Data\HintSeenInterface;
use Tirehub\BubbleHint\Model\HintSeen;
use Tirehub\BubbleHint\Model\ResourceModel\HintSeen as ResourceModelHintSeen;

class Collection extends AbstractCollection
{
    protected $_idFieldName = HintSeenInterface::HINT_SEEN_ID;

    protected function _construct(): void
    {
        $this->_init(
            HintSeen::class,
            ResourceModelHintSeen::class
        );
    }
}
