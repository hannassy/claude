<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Tirehub\BubbleHint\Model\HintFactory;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class UpdateHints implements DataPatchInterface
{
    private HintFactory $hintFactory;
    private HintRepositoryInterface $hintRepository;
    private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;

    private array $initHints = [
        [
            'name' => 'bulk-order-link',
            'text' => 'Got a big order to make? Bulk Orders is here to help you out. Make an order from the menu!'
        ],
        [
            'name' => 'navigation-billing',
            'text' => 'Formerly Invoices and Statements'
        ],
        [
            'name' => 'navigation',
            'text' => 'Left-side navigation is now located on top!'
        ],
        [
            'name' => 'recent-purchases',
            'text' => 'Click here to open a list of your Recent Purchases for the last 90 days!'
        ]
    ];

    public function __construct(
        HintFactory $hintFactory,
        HintRepositoryInterface $hintRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
    ) {
        $this->hintFactory = $hintFactory;
        $this->hintRepository = $hintRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
    }

    public function apply(): UpdateHints
    {
        $this->deleteExisting();

        foreach ($this->initHints as $data) {
            $hint = $this->hintFactory->create();
            $hint->setData($data);
            $this->hintRepository->save($hint);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [InsertInitHints::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function deleteExisting(): void
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteria->create();
        $list = $this->hintRepository->getList($searchCriteria);
        $items = $list->getItems();

        foreach ($items as $item) {
            $this->hintRepository->delete($item);
        }
    }
}
