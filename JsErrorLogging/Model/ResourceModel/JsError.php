<?php

declare(strict_types=1);

namespace Tirehub\JsErrorLogging\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class JsError extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('tirehub_jserror_logging', 'entity_id');
    }
}