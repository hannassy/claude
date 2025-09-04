<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Tirehub\BubbleHint\Api\Data\HintSeenInterface;

interface HintSeenRepositoryInterface
{
    public function save(HintSeenInterface $hintSeen): HintSeenInterface;

    public function get(int $hintseenId): HintSeenInterface;
    /** @phpstan-ignore-next-line  */
    public function getList(SearchCriteriaInterface $searchCriteria);

    public function delete(HintSeenInterface $hintSeen): bool;

    public function deleteById(int $hintseenId): bool;
}
