<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Location;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\App\Request\DataPersistorInterface;

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected $registry;
    protected $dataPersistor;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];

        // Check for persisted data first (from failed save attempts)
        $data = $this->dataPersistor->get('transfernetwork_location');
        if (!empty($data)) {
            $location = $this->collection->getNewEmptyItem();
            $location->setData($data);
            $this->loadedData[$location->getId()] = $location->getData();
            $this->dataPersistor->clear('transfernetwork_location');
            return $this->loadedData;
        }

        // Get location from registry (set by Edit controller)
        $location = $this->registry->registry('transfernetwork_location');

        if ($location && $location->getId()) {

            $itemData = $location->getData();
            $itemData['icon'] = [];
            $icon = $location->getData('icon');

            if ($icon) {
                $itemData['icon'] = [[
                    'name' => basename($icon),
                    'url' => $icon,
                    'type' => 'image/' . pathinfo($icon, PATHINFO_EXTENSION),
                ]];
            }

            $this->loadedData[$location->getId()] = $location->getData();
        } else {
            // For new locations, try to load from collection if ID exists in request
            $items = $this->collection->getItems();
            foreach ($items as $location) {
                $this->loadedData[$location->getId()] = $location->getData();
            }
        }

        return $this->loadedData;
    }

    public function getMeta(): array
    {
        $meta = parent::getMeta();

        return $meta;
    }
}