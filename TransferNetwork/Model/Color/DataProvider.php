<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\Color;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\Color\CollectionFactory;
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

        $data = $this->dataPersistor->get('transfernetwork_color');
        if (!empty($data)) {
            $color = $this->collection->getNewEmptyItem();
            $color->setData($data);
            $this->loadedData[$color->getId()] = $color->getData();
            $this->dataPersistor->clear('transfernetwork_color');
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $model) {
            $this->loadedData[$model->getId()] = $model->getData();
        }

        return $this->loadedData;
    }
}
