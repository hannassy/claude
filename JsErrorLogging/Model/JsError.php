<?php

declare(strict_types=1);

namespace Tirehub\JsErrorLogging\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\JsErrorLogging\Model\ResourceModel\JsError as JsErrorResource;

class JsError extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(JsErrorResource::class);
    }
}