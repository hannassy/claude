<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory as LocationCollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory as RelationCollectionFactory;

class Locations implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var LocationCollectionFactory
     */
    protected $locationCollectionFactory;

    /**
     * @var RelationCollectionFactory
     */
    protected $relationCollectionFactory;

    /**
     * Constructor
     *
     * @param JsonFactory $resultJsonFactory
     * @param LocationCollectionFactory $locationCollectionFactory
     * @param RelationCollectionFactory $relationCollectionFactory
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        LocationCollectionFactory $locationCollectionFactory,
        RelationCollectionFactory $relationCollectionFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->locationCollectionFactory = $locationCollectionFactory;
        $this->relationCollectionFactory = $relationCollectionFactory;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Get all locations
            $locationCollection = $this->locationCollectionFactory->create();
            $locations = [];

            foreach ($locationCollection as $location) {
                $locations[] = [
                    'id' => $location->getLocationId(),
                    'name' => $location->getLocationName(),
                    'latitude' => (float)$location->getLatitude(),
                    'longitude' => (float)$location->getLongitude()
                ];
            }

            // Get all relations with location details
            $relationCollection = $this->relationCollectionFactory->create();
            $relationCollection->joinLocationDetails();

            $relations = [];
            foreach ($relationCollection as $relation) {
                $relations[] = [
                    'from' => [
                        'id' => $relation->getLocationIdFrom(),
                        'name' => $relation->getData('from_location_name'),
                        'latitude' => (float)$relation->getData('from_latitude'),
                        'longitude' => (float)$relation->getData('from_longitude')
                    ],
                    'to' => [
                        'id' => $relation->getLocationIdTo(),
                        'name' => $relation->getData('to_location_name'),
                        'latitude' => (float)$relation->getData('to_latitude'),
                        'longitude' => (float)$relation->getData('to_longitude')
                    ]
                ];
            }

            return $result->setData([
                'success' => true,
                'locations' => $locations,
                'relations' => $relations
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
