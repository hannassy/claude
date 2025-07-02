<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class InsertLocationData implements DataPatchInterface, PatchRevertableInterface
{
    private const TABLE_NAME = 'tirehub_transfernetwork_location';

    private const LOCATIONS_DATA = [
        ['id' => 500, 'name' => 'ALLENTOWN', 'lat' => 40.60843, 'lng' => -75.49018, 'cluster' => 'RDC', 'color' => 'red', 'type' => 'RDC'],
        ['id' => 501, 'name' => 'DALLAS FT WORTH', 'lat' => 32.89748, 'lng' => -97.04087, 'cluster' => 'RDC', 'color' => 'red', 'type' => 'RDC'],
        ['id' => 502, 'name' => 'SAN BERNARDINO', 'lat' => 34.10834, 'lng' => -117.28977, 'cluster' => 'RDC', 'color' => 'red', 'type' => 'RDC'],

        ['id' => 100, 'name' => 'RALEIGH', 'lat' => 35.83739, 'lng' => -78.80882, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 101, 'name' => 'BALTIMORE', 'lat' => 39.15812, 'lng' => -76.66183, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 102, 'name' => 'WEST DEPTFORD', 'lat' => 39.82939, 'lng' => -75.20794, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 103, 'name' => 'TAMPA', 'lat' => 27.90798, 'lng' => -82.38531, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 104, 'name' => 'JACKSONVILLE', 'lat' => 30.37284, 'lng' => -81.78885, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 105, 'name' => 'FT LAUDERDALE', 'lat' => 26.26818, 'lng' => -80.16177, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 107, 'name' => 'ORLANDO', 'lat' => 28.43801, 'lng' => -81.37881, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 108, 'name' => 'LOUISVILLE', 'lat' => 38.15981, 'lng' => -85.88827, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 109, 'name' => 'KANSAS CITY', 'lat' => 39.13095, 'lng' => -94.56040, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 110, 'name' => 'COPPELL', 'lat' => 32.95540, 'lng' => -97.01501, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 111, 'name' => 'OKC', 'lat' => 35.46863, 'lng' => -97.50826, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 112, 'name' => 'CHARLOTTE', 'lat' => 35.23095, 'lng' => -80.85680, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 113, 'name' => 'HOUSTON NORTH', 'lat' => 29.94907, 'lng' => -95.35833, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 114, 'name' => 'SAN ANTONIO', 'lat' => 29.42580, 'lng' => -98.48693, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 115, 'name' => 'HARTFORD', 'lat' => 41.73730, 'lng' => -72.65130, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 116, 'name' => 'CLEVELAND', 'lat' => 41.48180, 'lng' => -81.68080, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 117, 'name' => 'NASHVILLE', 'lat' => 36.17450, 'lng' => -86.77000, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 118, 'name' => 'NORTH JERSEY', 'lat' => 40.78140, 'lng' => -74.22160, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 119, 'name' => 'SALT LAKE CITY', 'lat' => 40.73520, 'lng' => -111.88850, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 120, 'name' => 'DENVER', 'lat' => 39.75560, 'lng' => -104.99420, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 121, 'name' => 'DETROIT', 'lat' => 42.36980, 'lng' => -83.23570, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 122, 'name' => 'RICHMOND', 'lat' => 37.50460, 'lng' => -77.47530, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 123, 'name' => 'COLUMBUS', 'lat' => 39.98360, 'lng' => -82.98530, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 124, 'name' => 'JACKSON', 'lat' => 32.31160, 'lng' => -90.07580, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 125, 'name' => 'OMAHA', 'lat' => 41.26530, 'lng' => -96.01970, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 126, 'name' => 'CHICAGO', 'lat' => 41.87810, 'lng' => -87.62980, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 127, 'name' => 'PITTSBURGH', 'lat' => 40.44170, 'lng' => -79.99000, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 128, 'name' => 'PHOENIX', 'lat' => 33.44840, 'lng' => -112.07400, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 130, 'name' => 'SIMI VALLEY', 'lat' => 34.26940, 'lng' => -118.78150, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 131, 'name' => 'FONTANA', 'lat' => 34.09223, 'lng' => -117.43505, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 132, 'name' => 'MANASSAS', 'lat' => 38.74860, 'lng' => -77.48520, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 134, 'name' => 'PORTLAND', 'lat' => 45.51500, 'lng' => -122.67840, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 135, 'name' => 'LONG ISLAND', 'lat' => 40.78580, 'lng' => -73.08220, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 136, 'name' => 'ALBUQUERQUE', 'lat' => 35.08450, 'lng' => -106.65100, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 140, 'name' => 'CINCINNATI', 'lat' => 39.13620, 'lng' => -84.50300, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 141, 'name' => 'INDIANAPOLIS', 'lat' => 39.79110, 'lng' => -86.14810, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 142, 'name' => 'ALBANY', 'lat' => 42.68680, 'lng' => -73.82960, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 143, 'name' => 'BOSTON', 'lat' => 42.35920, 'lng' => -71.05740, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 145, 'name' => 'ALLENTOWN', 'lat' => 40.60843, 'lng' => -75.49018, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 146, 'name' => 'BUFFALO', 'lat' => 42.90460, 'lng' => -78.84890, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 147, 'name' => 'SYRACUSE', 'lat' => 43.04800, 'lng' => -76.14740, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 149, 'name' => 'GREENSBORO', 'lat' => 36.06930, 'lng' => -79.79200, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 150, 'name' => 'NORCROSS', 'lat' => 33.94010, 'lng' => -84.21450, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 152, 'name' => 'MIAMI', 'lat' => 25.77430, 'lng' => -80.19340, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 154, 'name' => 'NEW ORLEANS', 'lat' => 29.98300, 'lng' => -90.20100, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 160, 'name' => 'FRESNO', 'lat' => 36.74770, 'lng' => -119.77240, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 162, 'name' => 'SANTA ANA', 'lat' => 33.74560, 'lng' => -117.86760, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 163, 'name' => 'LOS ANGELES', 'lat' => 34.05220, 'lng' => -118.24370, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 166, 'name' => 'HAWAII', 'lat' => 21.30690, 'lng' => -157.85830, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 167, 'name' => 'SEATTLE', 'lat' => 47.60620, 'lng' => -122.33210, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 200, 'name' => 'SAN DIEGO', 'lat' => 32.71570, 'lng' => -117.16110, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 201, 'name' => 'BATON ROUGE', 'lat' => 30.44330, 'lng' => -91.18700, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 202, 'name' => 'AUSTIN', 'lat' => 30.31690, 'lng' => -97.77150, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 203, 'name' => 'MOBILE', 'lat' => 30.68780, 'lng' => -88.09300, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 204, 'name' => 'KNOXVILLE', 'lat' => 35.96080, 'lng' => -83.92070, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 205, 'name' => 'LAS VEGAS', 'lat' => 36.17250, 'lng' => -115.13990, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 206, 'name' => 'FORT MYERS', 'lat' => 26.64030, 'lng' => -81.87240, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 207, 'name' => 'MEMPHIS', 'lat' => 35.11740, 'lng' => -89.97110, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 208, 'name' => 'COLUMBIA', 'lat' => 34.00070, 'lng' => -81.03480, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 209, 'name' => 'KENNESAW', 'lat' => 34.02340, 'lng' => -84.61550, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 211, 'name' => 'HOUSTON SOUTH', 'lat' => 29.59230, 'lng' => -95.21600, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 212, 'name' => 'ARLINGTON', 'lat' => 32.70570, 'lng' => -97.12260, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 213, 'name' => 'SUFFOLK', 'lat' => 36.72820, 'lng' => -76.58350, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 214, 'name' => 'MCALLEN', 'lat' => 26.20340, 'lng' => -98.23000, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 215, 'name' => 'SACRAMENTO', 'lat' => 38.58160, 'lng' => -121.49440, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 216, 'name' => 'WEST PALM BEACH', 'lat' => 26.71530, 'lng' => -80.05340, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 217, 'name' => 'TUCSON', 'lat' => 32.22170, 'lng' => -110.92650, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 219, 'name' => 'TULSA', 'lat' => 36.13400, 'lng' => -95.93070, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 220, 'name' => 'NORTH CHICAGO', 'lat' => 42.32560, 'lng' => -87.84120, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 221, 'name' => 'SPOKANE', 'lat' => 47.65880, 'lng' => -117.42600, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 222, 'name' => 'SHREVEPORT', 'lat' => 32.52520, 'lng' => -93.75020, 'cluster' => '501 Cluster', 'color' => 'blue'],
        ['id' => 223, 'name' => 'OAKLAND', 'lat' => 37.80440, 'lng' => -122.27110, 'cluster' => '502 Cluster', 'color' => 'yellow'],
        ['id' => 224, 'name' => 'MINNEAPOLIS', 'lat' => 44.98300, 'lng' => -93.26840, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 225, 'name' => 'BIRMINGHAM', 'lat' => 33.52370, 'lng' => -86.79980, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 226, 'name' => 'GRAND RAPIDS', 'lat' => 42.96340, 'lng' => -85.66810, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 227, 'name' => 'GREENVILLE', 'lat' => 34.84950, 'lng' => -82.39850, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 228, 'name' => 'NORTH BOSTON', 'lat' => 42.48370, 'lng' => -71.20890, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 229, 'name' => 'ST LOUIS', 'lat' => 38.62700, 'lng' => -90.19940, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 230, 'name' => 'MIAMI', 'lat' => 25.77430, 'lng' => -80.19340, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 231, 'name' => 'MILWAUKEE', 'lat' => 43.03890, 'lng' => -87.90650, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 232, 'name' => 'SOUTH BOSTON', 'lat' => 42.33370, 'lng' => -71.04490, 'cluster' => '500 Cluster', 'color' => 'grey'],
        ['id' => 233, 'name' => 'CHARLESTON', 'lat' => 32.78020, 'lng' => -79.93090, 'cluster' => 'No RDC', 'color' => 'white'],
        ['id' => 234, 'name' => 'OAHU', 'lat' => 21.30690, 'lng' => -157.85830, 'cluster' => '502 Cluster', 'color' => 'yellow']
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $tableName = $this->moduleDataSetup->getTable(self::TABLE_NAME);
        $connection = $this->moduleDataSetup->getConnection();

        foreach (self::LOCATIONS_DATA as $location) {
            $rdcInventoryVisible = isset($location['type']) && $location['type'] === 'RDC' ? 1 : 0;

            $locationData = [
                'location_id' => $location['id'],
                'location_name' => $location['name'],
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
                'rdc_cluster' => $location['cluster'],
                'pin_color' => $location['color'],
                'active' => 1,
                'rdc_inventory_visible' => $rdcInventoryVisible
            ];

            $select = $connection->select()
                ->from($tableName, ['entity_id'])
                ->where('location_id = ?', $location['id']);

            $existingLocation = $connection->fetchOne($select);

            if (!$existingLocation) {
                $connection->insert($tableName, $locationData);
            } else {
                $connection->update(
                    $tableName,
                    $locationData,
                    ['location_id = ?' => $location['id']]
                );
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $tableName = $this->moduleDataSetup->getTable(self::TABLE_NAME);
        $connection = $this->moduleDataSetup->getConnection();

        $locationIds = array_column(self::LOCATIONS_DATA, 'id');

        $connection->delete($tableName, [
            'location_id IN (?)' => $locationIds
        ]);

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}
