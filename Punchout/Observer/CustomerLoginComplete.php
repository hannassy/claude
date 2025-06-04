<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class CustomerLoginComplete implements ObserverInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->isPunchoutMode->execute()) {
            // Force customer data reload on next page
            $this->customerSession->setData('force_customer_data_reload', true);

            // Update private content version to force section refresh
            $this->customerSession->setData('private_content_version', time());
        }
    }
}
