<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Punchout\Api\EnablePunchoutModeInterface;
use Magento\Customer\Model\Session as CustomerSession;

class EnablePunchoutMode implements EnablePunchoutModeInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute(string $buyerCookie): void
    {
        $this->customerSession->setData('is_punchout_mode', true);
        $this->customerSession->setData('buyer_cookie', $buyerCookie);
    }
}
