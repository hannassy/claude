<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Punchout\Api\EnablePunchoutModeInterface;
use Magento\Customer\Model\Session as CustomerSession;

class DisablePunchoutMode implements EnablePunchoutModeInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute(string $buyerCookie): void
    {
        $this->customerSession->unsetData('is_punchout_mode');
        $this->customerSession->unsetData('buyer_cookie');
    }
}
