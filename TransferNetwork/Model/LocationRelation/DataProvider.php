<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\LocationRelation;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory;
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

        $data = $this->dataPersistor->get('transfernetwork_relation');
        if (!empty($data)) {
            $relation = $this->collection->getNewEmptyItem();
            $relation->setData($data);
            $this->loadedData[$relation->getId()] = $relation->getData();
            $this->dataPersistor->clear('transfernetwork_relation');
            return $this->loadedData;
        }

        $relation = $this->registry->registry('transfernetwork_relation');

        if ($relation && $relation->getId()) {
            $this->loadedData[$relation->getId()] = $relation->getData();
        } else {
            $items = $this->collection->getItems();
            foreach ($items as $relation) {
                $this->loadedData[$relation->getId()] = $relation->getData();
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