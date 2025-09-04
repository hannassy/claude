<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Hint extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('tirehub_bubblehint', 'hint_id');
    }
}
