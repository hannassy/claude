<?php
declare(strict_types=1);

namespace Tirehub\Punchout\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class Punchout implements ArgumentInterface
{
    public function __construct(
        private readonly IsPunchoutModeInterface $isPunchoutMode,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function isPunchoutMode(): bool
    {
        return $this->isPunchoutMode->execute();
    }

    public function getPunchoutSessionId(): ?string
    {
        // Get buyer cookie from session if in punchout mode
        if ($this->isPunchoutMode()) {
            return $this->customerSession->getData('buyer_cookie');
        }

        return null;
    }

    public function getPunchoutRedirectUrl(): string
    {
        // You can determine the redirect URL based on various conditions
        // For example, redirect to cart if items were added
        $hasItems = $this->customerSession->getData('punchout_has_items') ?? false;

        if ($hasItems) {
            return 'checkout/cart';
        }

        return 'customer/account';
    }
}
