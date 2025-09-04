<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Tirehub\BubbleHint\Model\HintFactory;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class AddDeliveryNotification implements DataPatchInterface
{
    private HintFactory $hintFactory;
    private HintRepositoryInterface $hintRepository;
    private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;

    private array $data = [
        'name' => 'delivery-notification-link',
        'text' => 'Click the truck to view your pending delivery order details'
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

    public function apply(): AddDeliveryNotification
    {
        $this->disableExisting();
        $hint = $this->hintFactory->create();
        $hint->setData($this->data);
        $this->hintRepository->save($hint);

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

    private function disableExisting(): void
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteria->create();
        $list = $this->hintRepository->getList($searchCriteria);
        $items = $list->getItems();

        foreach ($items as $item) {
            $item->setIsActive(false);
            $this->hintRepository->save($item);
        }
    }
}
