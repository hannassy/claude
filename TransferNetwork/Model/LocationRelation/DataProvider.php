<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\LocationRelation;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;

class DataProvider extends AbstractDataProvider
{
    protected $loadedData = [];
    protected $dataPersistor;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $data = $this->dataPersistor->get('transfernetwork_relation');
        if (!empty($data)) {
            $relation = $this->collection->getNewEmptyItem();
            $relation->setData($data);
            $this->loadedData[$relation->getId()] = $relation->getData();
            $this->dataPersistor->clear('transfernetwork_relation');
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $model) {
            $data = $model->getData();

            if (!empty($data['cutoff_time'])) {
                $data['cutoff_time'] = substr($data['cutoff_time'], 0, 5);
            }

            $this->loadedData[$model->getId()] = $data;
        }

        return $this->loadedData;
    }
}
