<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Service;

use Tirehub\BubbleHint\Api\Data\GetCustomerNotSeenHintInterface;
use Tirehub\BubbleHint\Model\ResourceModel\Hint\CollectionFactory as HintCollectionFactory;

class GetCustomerNotSeenHint implements GetCustomerNotSeenHintInterface
{
    private const MAX_HINT_SEEN_COUNT = 1;

    private HintCollectionFactory $hintCollectionFactory;

    public function __construct(
        HintCollectionFactory $hintCollectionFactory
    ) {
        $this->hintCollectionFactory = $hintCollectionFactory;
    }

    // TODO change to joinProcessor
    public function execute(int $customerId): array
    {
        $result = [];

        $collection = $this->hintCollectionFactory->create();
        $collection->getSelect()
            ->joinLeft(
                ['hint_seen' => 'tirehub_bubblehint_seen'],
                'main_table.hint_id = hint_seen.hint_id
                and hint_seen.customer_id=' . $customerId,
                ''
            )
            ->limit(1)
            ->where('IFNULL(`hint_seen`.`seen_count`, 0) < ?', self::MAX_HINT_SEEN_COUNT)
            ->where('main_table.is_active=?', 1)
            ->order(['main_table.hint_id ASC']);

        if ($collection->getSize()) {
            foreach ($collection as $item) {
                $result[$item->getName()] = [
                    'id' => $item->getHintId(),
                    'text' => $item->getText()
                ];
            }
        }

        return $result;
    }
}
