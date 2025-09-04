<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Tirehub\BubbleHint\Api\Data\HintInterface;

interface HintRepositoryInterface
{
    public function save(HintInterface $hint): HintInterface;

    public function get(int $hintId): HintInterface;
    /** @phpstan-ignore-next-line  */
    public function getList(SearchCriteriaInterface $searchCriteria);

    public function delete(HintInterface $hint): bool;

    public function deleteById(int $hintId): bool;
}
