<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory;

class LocationOptions implements ArrayInterface
{
    protected $locationCollectionFactory;

    public function __construct(CollectionFactory $locationCollectionFactory)
    {
        $this->locationCollectionFactory = $locationCollectionFactory;
    }

    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->locationCollectionFactory->create();
        $collection->addFieldToFilter('active', 1);
        $collection->setOrder('location_id', 'ASC');

        foreach ($collection as $location) {
            $options[] = [
                'value' => $location->getLocationId(),
                'label' => $location->getLocationId() . ' ' . $location->getLocationName()
            ];
        }

        return $options;
    }
}
