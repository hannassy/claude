<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Location;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected $registry;
    protected $dataPersistor;
    protected $storeManager;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        DataPersistorInterface $dataPersistor,
        StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        $this->dataPersistor = $dataPersistor;
        $this->storeManager = $storeManager;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];

        $data = $this->dataPersistor->get('transfernetwork_location');
        if (!empty($data)) {
            $location = $this->collection->getNewEmptyItem();
            $location->setData($data);
            $this->loadedData[$location->getId()] = $location->getData();
            $this->dataPersistor->clear('transfernetwork_location');
            return $this->loadedData;
        }

        $location = $this->registry->registry('transfernetwork_location');

        if ($location && $location->getId()) {
            $itemData = $location->getData();
            $itemData['icon'] = $this->getIconFieldData($location->getData('icon'));
            $this->loadedData[$location->getId()] = $itemData;
        } else {
            $items = $this->collection->getItems();
            foreach ($items as $location) {
                $itemData = $location->getData();
                $itemData['icon'] = $this->getIconFieldData($location->getData('icon'));
                $this->loadedData[$location->getId()] = $itemData;
            }
        }

        return $this->loadedData;
    }

    private function getIconFieldData(?string $iconPath): array
    {
        if (!$iconPath) {
            return [];
        }

        $iconUrl = $this->getIconUrl($iconPath);
        if (!$iconUrl) {
            return [];
        }

        return [[
            'name' => basename($iconPath),
            'url' => $iconUrl,
            'type' => 'image/' . pathinfo($iconPath, PATHINFO_EXTENSION),
            'size' => 0
        ]];
    }

    private function getIconUrl(string $iconPath): ?string
    {
        try {
            $baseMediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

            if (str_starts_with($iconPath, 'http://') || str_starts_with($iconPath, 'https://')) {
                return $iconPath;
            }

            if (str_starts_with($iconPath, '/media/')) {
                $iconPath = ltrim($iconPath, '/media/');
            }

            if (!str_starts_with($iconPath, '/')) {
                $iconPath = '/' . $iconPath;
            }

            return rtrim($baseMediaUrl, '/') . $iconPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getMeta(): array
    {
        $meta = parent::getMeta();
        return $meta;
    }
}
