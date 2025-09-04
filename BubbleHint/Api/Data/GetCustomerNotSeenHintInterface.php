<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api\Data;

interface GetCustomerNotSeenHintInterface
{
    public function execute(int $customerId): array;
}
