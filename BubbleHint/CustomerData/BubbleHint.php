<?php

declare(strict_types=1);

namespace Tirehub\BubbleHint\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Tirehub\BubbleHint\Api\Data\GetCustomerNotSeenHintInterface;
use Tirehub\CssPriceAdjustment\Api\GetCurrentUserCssRoleInterface;

class BubbleHint implements SectionSourceInterface
{
    public function __construct(
        private readonly CurrentCustomer $currentCustomer,
        private readonly GetCustomerNotSeenHintInterface $getCustomerNotSeenHint,
        private readonly GetCurrentUserCssRoleInterface $getCurrentUserCssRole
    ) {
    }

    public function getSectionData(): array
    {
        $customerId = (int)$this->currentCustomer->getCustomerId();
        if (!$customerId) {
            return [];
        }

        $cssRole = $this->getCurrentUserCssRole->execute();
        if ($cssRole) {
            return [];
        }

        $hintData = $this->getCustomerNotSeenHint->execute($customerId);
        if (!$hintData) {
            return [];
        }

        return $hintData;
    }
}
