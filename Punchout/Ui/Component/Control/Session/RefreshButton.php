<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\Component\Control\Session;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class RefreshButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('Refresh'),
            'class' => 'primary',
            'on_click' => 'window.location.reload();',
            'sort_order' => 10
        ];
    }
}
