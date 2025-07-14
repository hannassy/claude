<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Service;

use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory as LocationCollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory as RelationCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\ApiMiddleware\Api\Request\GetLocationInfoInterface;
use Tirehub\Customer\Api\ConvertToTimeInterface;
use Exception;

class GetMapData
{
    public function __construct(
        private readonly LocationCollectionFactory $locationCollectionFactory,
        private readonly RelationCollectionFactory $relationCollectionFactory,
        private readonly GetLocationInfoInterface $getLocationInfo,
        private readonly ConvertToTimeInterface $convertToTime
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): array
    {
        return [
            'defaultLocations' => $this->getLocationData(),
            'routes' => $this->getRelationData()
        ];
    }

    /**
     * @throws LocalizedException
     */
    public function getLocationData(): array
    {
        $locations = [];

        try {
            $locationsInfo = $this->getLocationInfo->execute();
            $locationsInfo = $locationsInfo['locations'] ?? [];

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
                    'color' => $location->getPinColor(),
                    'isTirehub' => (int)$location->getIsTirehub(),
                    'address' => $this->getLocationAddress($locationInfo),
                    'openingHours' => $this->getLocationOpeningHours($locationInfo),
                    'cutoff' => $this->getLocationCutOff($locationInfo),
                ];

                if ($this->isRdcLocation($location)) {
                    $locationData['type'] = 'RDC';
                }

                $locations[] = $locationData;
            }
        } catch (LocalizedException|Exception $e) {
            var_dump($e->getMessage(), $location->getLocationId());
            die;
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

    private function isRdcLocation($location): bool
    {
        return $location->getRdcCluster() === 'RDC' || $location->getRdcInventoryVisible();
    }

    /**
     * @throws LocalizedException
     */
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

            return [
                'id' => (int)$location->getLocationId(),
                'name' => $location->getLocationName(),
                'lat' => (float)$location->getLatitude(),
                'lng' => (float)$location->getLongitude(),
                'cluster' => $this->determineCluster($location),
                'color' => $location->getPinColor(),
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

        //this.locations = _.map(this.defaultLocations, (location) => {
        //location.address = '814 44TH ST NW STE 102 AUBURN, WA98001-1754 253-856-1800';
        //    location.cutoff = {
        //    transferToPrimary: 'From 134 Portland',
        //        days: 1,
        //        time: '04:00 PM'
        //    };
        //    return location;
        //});

        $address = $locationsInfo['address'] ?? [];
        $street = $address['address1'] ?? '';
        $street .= ($address['address2'] ?? '') ? ', ' . $address['address2'] : '';
        $street .= ($address['address3'] ?? '') ? ', ' . $address['address3'] : '';

        return $street;

        //$result[] = [
        //    'city' => $address['city'],
        //    'address' => $street,
        //    'county' => $address['city'] . ', ' . $address['state'] . ' ' . $address['postalCode'],
        //    'telephone' => $address['phoneNumber'],
        //    'open' => 'Open: ' . strtoupper($willCallOpen . " - " . $willCallClose) . ' M-F, ' . strtoupper($willCallOpenSaturday . " - " . $willCallCloseSaturday) . ' SAT',
        //    'postcode' => $address['postalCode']
        //];
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

    private function getLocationCutOff(array $locationsInfo): string
    {
        if (!$locationsInfo) {
            return '';
        }

        return '';
    }
}
