<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class InsertLocationRelations implements DataPatchInterface, PatchRevertableInterface
{
    private const TABLE_NAME = 'tirehub_transfernetwork_location_relation';

    private const RELATIONS_DATA = [
        ['from' => 500, 'to' => 101],
        ['from' => 500, 'to' => 102],
        ['from' => 500, 'to' => 115],
        ['from' => 500, 'to' => 116],
        ['from' => 500, 'to' => 118],
        ['from' => 500, 'to' => 121],
        ['from' => 500, 'to' => 143],
        ['from' => 500, 'to' => 126],

        ['from' => 501, 'to' => 109],
        ['from' => 501, 'to' => 110],
        ['from' => 501, 'to' => 111],
        ['from' => 501, 'to' => 113],
        ['from' => 501, 'to' => 114],

        ['from' => 502, 'to' => 119],
        ['from' => 502, 'to' => 120],
        ['from' => 502, 'to' => 134],
        ['from' => 502, 'to' => 163],
        ['from' => 502, 'to' => 167],
        ['from' => 502, 'to' => 200],

        ['from' => 134, 'to' => 167],
        ['from' => 163, 'to' => 200],
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

        foreach (self::RELATIONS_DATA as $relation) {
            $relationData = [
                'location_id_from' => $relation['from'],
                'location_id_to' => $relation['to'],
                'active' => 1,
                'cutoff_days' => null
            ];

            $select = $connection->select()
                ->from($tableName, ['relation_id'])
                ->where('location_id_from = ?', $relation['from'])
                ->where('location_id_to = ?', $relation['to']);

            $existingRelation = $connection->fetchOne($select);

            if (!$existingRelation) {
                $connection->insert($tableName, $relationData);
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

        foreach (self::RELATIONS_DATA as $relation) {
            $connection->delete($tableName, [
                'location_id_from = ?' => $relation['from'],
                'location_id_to = ?' => $relation['to']
            ]);
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }
}