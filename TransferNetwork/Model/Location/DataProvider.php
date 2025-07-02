<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Location;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\Registry;

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected $registry;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];

        // Get location from registry (set by Edit controller)
        $location = $this->registry->registry('transfernetwork_location');

        if ($location && $location->getId()) {
            // Editing existing location
            $this->loadedData[$location->getId()] = $location->getData();
        } else {
            // New location - return empty array to show empty form
            $this->loadedData = [];
        }

        return $this->loadedData;
    }
}