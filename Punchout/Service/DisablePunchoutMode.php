<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Magento\Customer\Model\Session as CustomerSession;

class DisablePunchoutMode implements DisablePunchoutModeInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute(): void
    {
        $this->customerSession->unsetData('is_punchout_mode');
        $this->customerSession->unsetData('buyer_cookie');
    }
}
