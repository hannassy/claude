<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

use Magento\Sales\Api\Data\OrderInterface;

interface GetCustomerReferenceNumberInterface
{
    public function execute(OrderInterface $order): string;
}
