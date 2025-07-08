<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PinColor extends Column
{
    private const COLOR_MAP = [
        'grey' => '#95A5A6',
        'blue' => '#3498DB',
        'red' => '#E74C3C',
        'green' => '#27AE60',
        'yellow' => '#F1C40F',
        'purple' => '#9B59B6',
        'orange' => '#E67E22',
        'white' => '#ECF0F1',
        'black' => '#2C3E50'
    ];

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
                if (isset($item[$this->getData('name')])) {
                    $color = $item[$this->getData('name')];
                    $hexColor = self::COLOR_MAP[$color] ?? '#95A5A6';
                    $textColor = in_array($color, ['white', 'yellow']) ? '#000' : '#fff';

                    $item[$this->getData('name')] = sprintf(
                        '<span style="display: inline-block; padding: 4px 12px; background-color: %s; color: %s; border-radius: 4px; font-weight: 500;">%s</span>',
                        $hexColor,
                        $textColor,
                        ucfirst($color)
                    );
                }
            }
        }

        return $dataSource;
    }
}
