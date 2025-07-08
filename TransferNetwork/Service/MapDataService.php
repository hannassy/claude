<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Service;

use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory as LocationCollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory as RelationCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class MapDataService
{
    private const COLOR_MAPPING = [
        'RDC' => 'red',
        '500' => 'grey',
        '501' => 'blue',
        '502' => 'yellow',
        'default' => 'white'
    ];

    public function __construct(
        private readonly LocationCollectionFactory $locationCollectionFactory,
        private readonly RelationCollectionFactory $relationCollectionFactory
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function getMapData(): array
    {
        return [
            'locations' => $this->getLocationData(),
            'routes' => $this->getRelationData()
        ];
    }

    public function getLocationData(): array
    {
        $locations = [];

        try {
            $collection = $this->locationCollectionFactory->create();
            $collection->addFieldToFilter('active', 1)
                ->setOrder('location_id', 'ASC');

            foreach ($collection as $location) {
                $locationData = [
                    'id' => (int)$location->getLocationId(),
                    'name' => $location->getLocationName(),
                    'lat' => (float)$location->getLatitude(),
                    'lng' => (float)$location->getLongitude(),
                    'cluster' => $this->determineCluster($location),
                    'color' => $this->determineColor($location),
                ];

                if ($this->isRdcLocation($location)) {
                    $locationData['type'] = 'RDC';
                }

                $locations[] = $locationData;
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Unable to load location data: %1', $e->getMessage()));
        }

        return $locations;
    }

    public function getRelationData(): array
    {
        $relations = [];

        try {
            $collection = $this->relationCollectionFactory->create();
            $collection->addFieldToFilter('main_table.active', 1)
                ->joinLocationDetails();

            foreach ($collection as $relation) {
                $fromLocationId = (int)$relation->getLocationIdFrom();
                $toLocationId = (int)$relation->getLocationIdTo();

                $relations[] = [
                    'from' => $fromLocationId,
                    'to' => $toLocationId,
                    'type' => $this->determineRelationType($fromLocationId, $toLocationId)
                ];
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Unable to load relation data: %1', $e->getMessage()));
        }

        return $relations;
    }

    private function determineRelationType(int $fromLocationId, int $toLocationId): string
    {
        $fromLocation = $this->getLocationById($fromLocationId);
        $toLocation = $this->getLocationById($toLocationId);

        if (!$fromLocation || !$toLocation) {
            return 'unknown';
        }

        $fromType = $fromLocation['type'] ?? 'TLC';
        $toType = $toLocation['type'] ?? 'TLC';

        if ($fromType === 'RDC' && $toType === 'TLC') {
            return 'rdc-tlc';
        }

        if ($fromType === 'TLC' && $toType === 'RDC') {
            return 'tlc-rdc';
        }

        if ($fromType === 'TLC' && $toType === 'TLC') {
            return 'tlc-tlc';
        }

        if ($fromType === 'RDC' && $toType === 'RDC') {
            return 'rdc-rdc';
        }

        return 'unknown';
    }

    private function determineCluster($location): string
    {
        $rdcCluster = $location->getRdcCluster();

        if (empty($rdcCluster)) {
            return 'No RDC';
        }

        if ($rdcCluster === 'RDC') {
            return 'RDC';
        }

        if (str_contains($rdcCluster, 'Cluster')) {
            return $rdcCluster;
        }

        return $rdcCluster . ' Cluster';
    }

    private function determineColor($location): string
    {
        $pinColor = $location->getPinColor();

        if (!empty($pinColor)) {
            return $pinColor;
        }

        $rdcCluster = $location->getRdcCluster();
        if (!$rdcCluster) {
            return self::COLOR_MAPPING['default'];
        }

        if ($rdcCluster === 'RDC') {
            return self::COLOR_MAPPING['RDC'];
        }

        foreach (['500', '501', '502'] as $clusterCode) {
            if (str_contains($rdcCluster, $clusterCode)) {
                return self::COLOR_MAPPING[$clusterCode] ?? '';
            }
        }

        return self::COLOR_MAPPING['default'];
    }

    private function isRdcLocation($location): bool
    {
        return $location->getRdcCluster() === 'RDC' || $location->getRdcInventoryVisible();
    }

    public function getLocationById(int $locationId): ?array
    {
        try {
            $collection = $this->locationCollectionFactory->create();
            $collection->addFieldToFilter('location_id', $locationId)
                ->addFieldToFilter('active', 1);

            $location = $collection->getFirstItem();

            if (!$location->getId()) {
                return null;
            }

            return [
                'id' => (int)$location->getLocationId(),
                'name' => $location->getLocationName(),
                'lat' => (float)$location->getLatitude(),
                'lng' => (float)$location->getLongitude(),
                'cluster' => $this->determineCluster($location),
                'color' => $this->determineColor($location),
                'type' => $this->isRdcLocation($location) ? 'RDC' : 'TLC'
            ];
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Unable to load location: %1', $e->getMessage()));
        }
    }

    public function getLocationsByCluster(string $cluster): array
    {
        $collection = $this->locationCollectionFactory->create();
        $collection->addFieldToFilter('active', 1)
            ->addFieldToFilter('rdc_cluster', $cluster)
            ->setOrder('location_name', 'ASC');

        $locations = [];
        foreach ($collection as $location) {
            $locations[] = [
                'id' => (int)$location->getLocationId(),
                'name' => $location->getLocationName(),
                'lat' => (float)$location->getLatitude(),
                'lng' => (float)$location->getLongitude(),
                'cluster' => $this->determineCluster($location),
                'color' => $this->determineColor($location),
            ];
        }

        return $locations;
    }
}
