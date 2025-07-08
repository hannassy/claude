<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CutoffTime extends Column
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
                if (isset($item[$this->getData('name')]) && $item[$this->getData('name')]) {
                    try {
                        $time = new \DateTime($item[$this->getData('name')]);
                        $item[$this->getData('name')] = $time->format('g:i A');
                    } catch (\Exception $e) {
                        $item[$this->getData('name')] = $item[$this->getData('name')];
                    }
                }
            }
        }

        return $dataSource;
    }
}
