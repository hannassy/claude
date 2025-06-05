<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Plugin\Checkout;

use Magento\Customer\Model\Session;
use Tirehub\Checkout\Api\IsDeniedB2BOrderingInterface as Subject;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class IsDeniedB2BOrdering
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    public function afterExecute(Subject $subject, bool $result): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        if (!$this->isPunchoutMode->execute()) {
            return $result;
        }

        return false;
    }
}
