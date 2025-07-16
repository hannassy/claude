<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ColorDisplay extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['color_code']) && isset($item['color_name'])) {
                    $item[$this->getData('name')] = sprintf(
                        '<div style="display: flex; align-items: center;">
                            <span style="display: inline-block; width: 20px; height: 20px; background-color: %s; border: 1px solid #ccc; margin-right: 8px; border-radius: 3px;"></span>
                            <span>%s</span>
                        </div>',
                        $item['color_code'],
                        $item['color_name']
                    );
                }
            }
        }

        return $dataSource;
    }
}
