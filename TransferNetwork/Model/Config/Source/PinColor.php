<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PinColor implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'grey', 'label' => __('Grey #95A5A6')],
            ['value' => 'blue', 'label' => __('Blue #3498DB')],
            ['value' => 'red', 'label' => __('Red #E74C3C')],
            ['value' => 'green', 'label' => __('Green #27AE60')],
            ['value' => 'yellow', 'label' => __('Yellow #F1C40F')],
            ['value' => 'purple', 'label' => __('Purple #9B59B6')],
            ['value' => 'orange', 'label' => __('Orange #E67E22')],
            ['value' => 'white', 'label' => __('White #ECF0F1')],
            ['value' => 'black', 'label' => __('Black #2C3E50')]
        ];
    }
}
