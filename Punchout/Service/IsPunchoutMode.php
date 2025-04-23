<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Punchout\Api\IsPunchoutModeInterface;
use Magento\Customer\Model\Session as CustomerSession;

class IsPunchoutMode implements IsPunchoutModeInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute(): bool
    {
        return (bool)$this->customerSession->getData('is_punchout_mode');
    }
}
