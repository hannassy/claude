<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Block\Adminhtml\Color\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\Registry;
use Magento\Backend\Block\Widget\Context;

class DeleteButton implements ButtonProviderInterface
{
    protected $registry;
    protected $context;

    public function __construct(
        Context $context,
        Registry $registry
    ) {
        $this->context = $context;
        $this->registry = $registry;
    }

    public function getButtonData(): array
    {
        $data = [];
        $color = $this->registry->registry('transfernetwork_color');
        if ($color && $color->getId()) {
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                        'Are you sure you want to delete this color?'
                    ) . '\', \'' . $this->getDeleteUrl() . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }

    public function getDeleteUrl(): string
    {
        $color = $this->registry->registry('transfernetwork_color');
        return $this->context->getUrlBuilder()->getUrl('*/*/delete', ['entity_id' => $color->getId()]);
    }
}
