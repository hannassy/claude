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
        // Debug logging
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/location_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $logger->info('DataProvider::getData() called');

        if (isset($this->loadedData)) {
            $logger->info('Returning cached data: ' . json_encode($this->loadedData));
            return $this->loadedData;
        }

        $this->loadedData = [];

        // Get location from registry (set by Edit controller)
        $location = $this->registry->registry('transfernetwork_location');

        if ($location && $location->getId()) {
            // Editing existing location
            $logger->info('Found location in registry with ID: ' . $location->getId());
            $locationData = $location->getData();

            // Ensure data is properly formatted for the form
            $formData = [
                'entity_id' => $location->getId(),
                'location_id' => $location->getLocationId(),
                'location_name' => $location->getLocationName(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'rdc_cluster' => $location->getRdcCluster(),
                'pin_color' => $location->getPinColor(),
                'active' => $location->getActive() ? '1' : '0',
                'rdc_inventory_visible' => $location->getRdcInventoryVisible() ? '1' : '0'
            ];

            $logger->info('Formatted form data: ' . json_encode($formData));
            $this->loadedData[$location->getId()] = $formData;
        } else {
            // New location - return empty array to show empty form
            $logger->info('No location in registry - new location form');
            $this->loadedData = [];
        }

        $logger->info('Final loadedData count: ' . count($this->loadedData));
        return $this->loadedData;
    }

    public function getMeta(): array
    {
        $meta = parent::getMeta();

        // Debug log meta
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/location_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('DataProvider::getMeta() called');

        return $meta;
    }
}