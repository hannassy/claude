<?php

declare(strict_types=1);

namespace Tirehub\JsErrorLogging\Model\ResourceModel\JsError;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tirehub\JsErrorLogging\Model\JsError;
use Tirehub\JsErrorLogging\Model\ResourceModel\JsError as JsErrorResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(JsError::class, JsErrorResource::class);
    }
}