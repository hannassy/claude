<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tirehub\TransferNetwork\Model\ResourceModel\Color\CollectionFactory as ColorCollectionFactory;

class LocationColor extends Column
{
    protected $colorCollectionFactory;
    protected $colors = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ColorCollectionFactory $colorCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->colorCollectionFactory = $colorCollectionFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $this->loadColors();

            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['color_id']) && $item['color_id']) {
                    $colorId = (int)$item['color_id'];
                    if (isset($this->colors[$colorId])) {
                        $color = $this->colors[$colorId];
                        $item[$this->getData('name')] = sprintf(
                            '<div style="display: flex; align-items: center;">
                                <span style="display: inline-block; width: 20px; height: 20px; background-color: %s; border: 1px solid #ccc; margin-right: 8px; border-radius: 3px;"></span>
                                <span>%s</span>
                            </div>',
                            $color['color_code'],
                            $color['color_name']
                        );
                    } else {
                        $item[$this->getData('name')] = '<span style="color: #999;">No Color</span>';
                    }
                } else {
                    $item[$this->getData('name')] = '<span style="color: #999;">No Color</span>';
                }
            }
        }

        return $dataSource;
    }

    protected function loadColors(): void
    {
        if (empty($this->colors)) {
            $collection = $this->colorCollectionFactory->create();
            foreach ($collection as $color) {
                $this->colors[$color->getId()] = [
                    'color_name' => $color->getColorName(),
                    'color_code' => $color->getColorCode()
                ];
            }
        }
    }
}
