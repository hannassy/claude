<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\ResourceModel\Session as ResourceModel;

class Session extends AbstractModel implements SessionInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
