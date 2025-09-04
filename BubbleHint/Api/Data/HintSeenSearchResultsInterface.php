<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface HintSeenSearchResultsInterface extends SearchResultsInterface
{
    public function getItems(): array;

    public function setItems(array $items);
}
