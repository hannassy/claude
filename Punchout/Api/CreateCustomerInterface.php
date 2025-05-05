<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface CreateCustomerInterface
{
    public function execute(array $extrinsics, string $dealerCode): int;
}
