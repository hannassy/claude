<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Source\Session;

use Magento\Framework\Data\OptionSourceInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => SessionInterface::STATUS_NEW, 'label' => __('New')],
            ['value' => SessionInterface::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => SessionInterface::STATUS_COMPLETED, 'label' => __('Completed')],
            ['value' => SessionInterface::STATUS_ERROR, 'label' => __('Error')]
        ];
    }
}
