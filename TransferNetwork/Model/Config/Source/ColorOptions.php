<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tirehub\TransferNetwork\Model\ResourceModel\Color\CollectionFactory;

class ColorOptions implements OptionSourceInterface
{
    protected $colorCollectionFactory;

    public function __construct(CollectionFactory $colorCollectionFactory)
    {
        $this->colorCollectionFactory = $colorCollectionFactory;
    }

    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->colorCollectionFactory->create();
        $collection->setOrder('color_name', 'ASC');

        foreach ($collection as $color) {
            $options[] = [
                'value' => $color->getId(),
                'label' => $color->getColorName() . ' (' . $color->getColorCode() . ')'
            ];
        }

        return $options;
    }
}
