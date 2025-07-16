<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class MigrateColorsData implements DataPatchInterface, PatchRevertableInterface
{
    private const COLOR_TABLE = 'tirehub_transfernetwork_color';
    private const LOCATION_TABLE = 'tirehub_transfernetwork_location';

    private const DEFAULT_COLORS = [
        'grey' => '#95A5A6',
        'blue' => '#3498DB',
        'red' => '#E74C3C',
        'green' => '#27AE60',
        'yellow' => '#F1C40F',
        'purple' => '#9B59B6',
        'orange' => '#E67E22',
        'white' => '#ECF0F1',
        'black' => '#2C3E50'
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

        $connection = $this->moduleDataSetup->getConnection();
        $colorTable = $this->moduleDataSetup->getTable(self::COLOR_TABLE);
        $locationTable = $this->moduleDataSetup->getTable(self::LOCATION_TABLE);

        $colorMapping = [];

        foreach (self::DEFAULT_COLORS as $colorName => $colorCode) {
            $colorData = [
                'color_name' => ucfirst($colorName),
                'color_code' => $colorCode
            ];

            $select = $connection->select()
                ->from($colorTable, ['entity_id'])
                ->where('color_name = ?', ucfirst($colorName));

            $existingColorId = $connection->fetchOne($select);

            if (!$existingColorId) {
                $connection->insert($colorTable, $colorData);
                $colorId = $connection->lastInsertId($colorTable);
            } else {
                $colorId = $existingColorId;
            }

            $colorMapping[$colorName] = $colorId;
        }

        if ($connection->tableColumnExists($locationTable, 'pin_color')) {
            $select = $connection->select()
                ->from($locationTable, ['entity_id', 'pin_color'])
                ->where('pin_color IS NOT NULL');

            $locations = $connection->fetchAll($select);

            foreach ($locations as $location) {
                $pinColor = $location['pin_color'];
                $colorId = $colorMapping[$pinColor] ?? null;

                if ($colorId) {
                    $connection->update(
                        $locationTable,
                        ['color_id' => $colorId],
                        ['entity_id = ?' => $location['entity_id']]
                    );
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $colorTable = $this->moduleDataSetup->getTable(self::COLOR_TABLE);
        $locationTable = $this->moduleDataSetup->getTable(self::LOCATION_TABLE);

        $colorNames = array_map('ucfirst', array_keys(self::DEFAULT_COLORS));

        $connection->delete($colorTable, [
            'color_name IN (?)' => $colorNames
        ]);

        if ($connection->tableColumnExists($locationTable, 'color_id')) {
            $connection->update(
                $locationTable,
                ['color_id' => null],
                ['color_id IS NOT NULL']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}
