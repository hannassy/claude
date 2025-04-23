<?php
declare(strict_types=1);

namespace Tirehub\Punchout\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class Punchout implements SectionSourceInterface
{
    public function __construct(
        private readonly CurrentCustomer $currentCustomer,
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    public function getSectionData(): array
    {
        if (!$this->currentCustomer->getCustomerId()) {
            return [];
        }

        return [
            'mode' => $this->isPunchoutMode->execute()
        ];
    }
}
