<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Tirehub\BubbleHint\Model\HintFactory;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;

class InsertInitHints implements DataPatchInterface
{
    private HintFactory $hintFactory;
    private HintRepositoryInterface $hintRepository;

    private array $initHints = [
        [
            'name' => 'bulk-order-link',
            'text' => 'Got a big order to make? Bulk Orders is here to help you out. Make an order from the menu!'
        ],
        [
            'name' => 'drop-ship-button',
            // phpcs:ignore
            'text' => "Dropship gives you the opportunity to order tires directly to your customer’s address and information!"
        ],
        [
            'name' => 'recent-purchases',
            'text' => 'Click here to open a list of your Recent Purchases for the last 90 days!'
        ]
    ];

    public function __construct(
        HintFactory $hintFactory,
        HintRepositoryInterface $hintRepository
    ) {
        $this->hintFactory = $hintFactory;
        $this->hintRepository = $hintRepository;
    }

    public function apply(): InsertInitHints
    {
        foreach ($this->initHints as $data) {
            $hint = $this->hintFactory->create();
            $hint->setData($data);
            $this->hintRepository->save($hint);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
