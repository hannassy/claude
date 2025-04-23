<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\ResourceModel\Session;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\Punchout\Model\Session;
use Tirehub\Punchout\Model\ResourceModel\Session as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'punchout_session_collection';

    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(Session::class, ResourceModel::class);
    }
}
