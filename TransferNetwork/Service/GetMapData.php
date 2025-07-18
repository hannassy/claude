<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Service;

use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory as LocationCollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory as RelationCollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color\CollectionFactory as ColorCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\ApiMiddleware\Api\Request\GetLocationInfoInterface;
use Tirehub\Customer\Api\ConvertToTimeInterface;
use Exception;

class GetMapData
{
    public function __construct(
        private readonly LocationCollectionFactory $locationCollectionFactory,
        private readonly RelationCollectionFactory $relationCollectionFactory,
        private readonly ColorCollectionFactory $colorCollectionFactory,
        private readonly GetLocationInfoInterface $getLocationInfo,
        private readonly ConvertToTimeInterface $convertToTime
    ) {
    }

    public function execute(): array
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
            $locationsInfo = $this->getLocationInfo->execute();
            $locationsInfo = $locationsInfo['locations'] ?? [];
            $colors = $this->getColors();

            $collection = $this->locationCollectionFactory->create();
            $collection->addFieldToFilter('active', 1)
                ->setOrder('location_id', 'ASC');

            foreach ($collection as $location) {
                $locationInfo = $this->getLocationInfo($location->getLocationId(), $locationsInfo);

                $locationData = [
                    'id' => (int)$location->getLocationId(),
                    'name' => $location->getLocationName(),
                    'lat' => (float)$location->getLatitude(),
                    'lng' => (float)$location->getLongitude(),
                    'cluster' => $this->determineCluster($location),
                    'color' => $this->getLocationColor($location->getColorId(), $colors),
                    'isTirehub' => (int)$location->getIsTirehub(),
                    'address' => $this->getLocationAddress($locationInfo),
                    'openingHours' => $this->getLocationOpeningHours($locationInfo),
                    'cutoff' => $this->getLocationCutOff($location),
                    'icon' => $location->getIcon(),
                ];

                if ($this->isRdcLocation($location)) {
                    $locationData['type'] = 'RDC';
                }

                $locations[] = $locationData;
            }
        } catch (LocalizedException|Exception $e) {
            throw new LocalizedException(__('Unable to load location data: %1', $e->getMessage()));
        }

        return $locations;
    }

    public function getRelationData(): array
    {
        $relations = [];

        try {
            $colors = $this->getColors();

            $collection = $this->relationCollectionFactory->create();
            $collection->addFieldToFilter('main_table.active', 1)
                ->joinLocationDetails();

            foreach ($collection as $relation) {
                $fromLocationId = (int)$relation->getLocationIdFrom();
                $toLocationId = (int)$relation->getLocationIdTo();
                $colorId = $relation->getColorId();

                $relationData = [
                    'from' => $fromLocationId,
                    'to' => $toLocationId,
                    'type' => $this->determineRelationType($fromLocationId, $toLocationId),
                    'color' => $this->getRelationColor($colorId, $colors),
                    'weight' => $this->getRelationWeight($colorId)
                ];

                $relations[] = $relationData;
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Unable to load relation data: %1', $e->getMessage()));
        }

        return $relations;
    }

    private function getColors(): array
    {
        $colors = [];
        $collection = $this->colorCollectionFactory->create();

        foreach ($collection as $color) {
            $colors[$color->getId()] = $color->getColorCode();
        }

        return $colors;
    }

    private function getLocationColor(?int $colorId, array $colors): string
    {
        if ($colorId && isset($colors[$colorId])) {
            return $colors[$colorId];
        }

        return '#95A5A6';
    }

    private function getRelationColor(?int $colorId, array $colors): string
    {
        if ($colorId && isset($colors[$colorId])) {
            return $colors[$colorId];
        }

        return '#ff6b35';
    }

    private function getRelationWeight(?int $colorId): int
    {
        return $colorId ? 4 : 2;
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

    private function isRdcLocation($location): bool
    {
        return $location->getRdcCluster() === 'RDC' || $location->getRdcInventoryVisible();
    }

    private function getLocationById(int $locationId): ?array
    {
        try {
            $collection = $this->locationCollectionFactory->create();
            $collection->addFieldToFilter('location_id', $locationId)
                ->addFieldToFilter('active', 1);

            $location = $collection->getFirstItem();

            if (!$location->getId()) {
                return null;
            }

            $colors = $this->getColors();

            return [
                'id' => (int)$location->getLocationId(),
                'name' => $location->getLocationName(),
                'lat' => (float)$location->getLatitude(),
                'lng' => (float)$location->getLongitude(),
                'cluster' => $this->determineCluster($location),
                'color' => $this->getLocationColor($location->getColorId(), $colors),
                'type' => $this->isRdcLocation($location) ? 'RDC' : 'TLC'
            ];
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Unable to load location: %1', $e->getMessage()));
        }
    }

    private function getLocationInfo(int $locationId, array $locationsInfo): array
    {
        $locationInfo = array_filter($locationsInfo, function ($item) use ($locationId) {
            return $item['locationId'] == $locationId;
        });

        return reset($locationInfo) ?: [];
    }

    private function getLocationAddress(array $locationsInfo): string
    {
        if (!$locationsInfo) {
            return '';
        }

        $address = $locationsInfo['address'] ?? [];
        $street = $address['address1'] ?? '';
        $street .= ($address['address2'] ?? '') ? ', ' . $address['address2'] : '';
        $street .= ($address['address3'] ?? '') ? ', ' . $address['address3'] : '';
        $street .= $address['city'] ?? '';
        $street .= ', ' . $address['state'] ?? '';
        $street .= ' ' . $address['postalCode'] ?? '';
        $street .= ' ' . $address['phoneNumber'] ?? '';

        return $street;
    }

    private function getLocationOpeningHours(array $locationsInfo): array
    {
        if (!$locationsInfo) {
            return [];
        }

        $address = $locationsInfo['address'] ?? [];
        $timezone = $address['timezone'] ?? 'EASTERN';
        $willCallOpen = $locationsInfo['willCallOpen']
            ? $this->convertToTime->execute($locationsInfo['willCallOpen'], $timezone, false, 'G:i A')
            : null;
        $willCallClose = $locationsInfo['willCallClose']
            ? $this->convertToTime->execute($locationsInfo['willCallClose'], $timezone, false, 'G:i A')
            : null;
        $willCallOpenSaturday = $locationsInfo['willCallOpenSaturday']
            ? $this->convertToTime->execute($locationsInfo['willCallOpenSaturday'], $timezone, false, 'G:i A')
            : null;
        $willCallCloseSaturday = $locationsInfo['willCallCloseSaturday']
            ? $this->convertToTime->execute($locationsInfo['willCallCloseSaturday'], $timezone, false, 'G:i A')
            : null;

        return [
            [
                'weekDay' => 'Monday - Friday',
                'time' => $willCallOpen . ' - ' . $willCallClose
            ],
            [
                'weekDay' => 'Saturday',
                'time' => $willCallOpenSaturday . ' - ' . $willCallCloseSaturday
            ]
        ];
    }

    private function getLocationCutOff($location): array
    {
        if (!$location || !$location->getLocationId()) {
            return [];
        }

        $locationId = $location->getLocationId();

        try {
            $relationCollection = $this->relationCollectionFactory->create();
            $relationCollection->addFieldToFilter('main_table.location_id_from', $locationId)
                ->addFieldToFilter('main_table.active', 1)
                ->joinLocationDetails();

            $cutoffData = [];

            foreach ($relationCollection as $relation) {
                $toLocationName = $relation->getData('to_location_name');
                $cutoffDays = $relation->getCutoffDays();
                $cutoffTime = $relation->getCutoffTime();

                if ($toLocationName) {
                    $formattedTime = '';
                    if ($cutoffTime) {
                        try {
                            $time = new \DateTime($cutoffTime);
                            $formattedTime = $time->format('g:i A');
                        } catch (\Exception $e) {
                            $formattedTime = $cutoffTime;
                        }
                    }

                    $cutoffData[] = [
                        'to' => $relation->getLocationIdTo() . ' ' . $toLocationName,
                        'days' => $cutoffDays ? (string)$cutoffDays : '',
                        'time' => $formattedTime
                    ];
                }
            }

            return $cutoffData;
        } catch (\Exception $e) {
            return [];
        }
    }
}
