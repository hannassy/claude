<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\Punchout\Api\Data\ItemInterface;
use Tirehub\Punchout\Model\ResourceModel\Item as ResourceModel;

class Item extends AbstractModel implements ItemInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
