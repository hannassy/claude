<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getCustomerEmailTemplate(): string
    {
        return (string)$this->scopeConfig->getValue('punchout/customer/email_template');
    }

    public function isDebugMode(): bool
    {
        return (bool)$this->scopeConfig->getValue('punchout/dev/is_debug_mode');
    }

    public function isProcessItemRedirect(): bool
    {
        return (bool)$this->scopeConfig->getValue('punchout/dev/process_item_redirect');
    }
}
