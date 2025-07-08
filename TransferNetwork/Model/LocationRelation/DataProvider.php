<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model\LocationRelation;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $model) {
            $data = $model->getData();

            // Format cutoff_time for the time picker
            if (!empty($data['cutoff_time'])) {
                // Remove seconds for the picker
                $data['cutoff_time'] = substr($data['cutoff_time'], 0, 5);
            }

            $this->loadedData[$model->getId()] = $data;
        }

        return $this->loadedData;
    }
}
