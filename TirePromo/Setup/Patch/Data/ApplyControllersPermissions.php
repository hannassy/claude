<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class ApplyControllersPermissions implements DataPatchInterface, PatchRevertableInterface
{
    private const TABLE_NAME_ELEMENT = 'silk_access_element';
    private const TABLE_NAME_ROLE = 'silk_access_role_right';
    private const MODULE_NAME = 'Tirehub_TirePromo';
    private const CONTROLLERS = [
        'index' => [
            'index'
        ],
    ];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {

        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply(): ApplyControllersPermissions
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach (self::CONTROLLERS as $controllerName => $actions) {
            foreach ($actions as $action) {
                $this->addControllerPermission($controllerName, $action);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $elementTable = $this->moduleDataSetup->getTable(self::TABLE_NAME_ELEMENT);
        $roleTable = $this->moduleDataSetup->getTable(self::TABLE_NAME_ROLE);

        $selectSql = $this->moduleDataSetup->getConnection()
            ->select()
            ->from($elementTable, ['id'])
            ->where('module = ?', self::MODULE_NAME);

        $elementIds = $this->moduleDataSetup->getConnection()->fetchCol($selectSql);

        if (count($elementIds)) {
            $this->moduleDataSetup->getConnection()->delete($roleTable, ['element_id IN (?)' => $elementIds]);
            $this->moduleDataSetup->getConnection()->delete($elementTable, ['id IN (?)' => $elementIds]);
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function addControllerPermission(string $controller, string $action): void
    {
        $elementTable = $this->moduleDataSetup->getTable(self::TABLE_NAME_ELEMENT);
        $roleTable = $this->moduleDataSetup->getTable(self::TABLE_NAME_ROLE);

        $elementData = [
            'module' => self::MODULE_NAME,
            'controller' => $controller,
            'action' => $action,
            'block' => '',
            'action_type' => 'Access'
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate($elementTable, $elementData);

        /** @phpstan-ignore-next-line ("Call to an undefined method") */
        $lastInsertId = $this->moduleDataSetup->getConnection()->lastInsertId($elementTable);

        for ($i = 1; $i <= 3; $i++) {
            $roleData = [
                'element_id' => $lastInsertId,
                'role_id' => $i,
                'access' => 1
            ];

            $this->moduleDataSetup->getConnection()->insertOnDuplicate($roleTable, $roleData);
        }
    }
}
