<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddColorToRelations implements DataPatchInterface, PatchRevertableInterface
{
    private const RELATION_TABLE = 'tirehub_transfernetwork_location_relation';
    private const COLOR_TABLE = 'tirehub_transfernetwork_color';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            MigrateColorsData::class
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $relationTable = $this->moduleDataSetup->getTable(self::RELATION_TABLE);
        $colorTable = $this->moduleDataSetup->getTable(self::COLOR_TABLE);

        if (!$connection->tableColumnExists($relationTable, 'color_id')) {
            $this->moduleDataSetup->getConnection()->endSetup();
            return $this;
        }

        $redColorSelect = $connection->select()
            ->from($colorTable, ['entity_id'])
            ->where('color_name = ?', 'Red')
            ->limit(1);

        $redColorId = $connection->fetchOne($redColorSelect);

        if ($redColorId) {
            $select = $connection->select()
                ->from($relationTable, ['relation_id'])
                ->where('color_id IS NULL');

            $relationsWithoutColor = $connection->fetchCol($select);

            if (!empty($relationsWithoutColor)) {
                $connection->update(
                    $relationTable,
                    ['color_id' => $redColorId],
                    ['relation_id IN (?)' => $relationsWithoutColor]
                );
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $relationTable = $this->moduleDataSetup->getTable(self::RELATION_TABLE);

        if ($connection->tableColumnExists($relationTable, 'color_id')) {
            $connection->update(
                $relationTable,
                ['color_id' => null],
                ['color_id IS NOT NULL']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}
