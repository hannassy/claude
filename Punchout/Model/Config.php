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

    public function getTestEmails(): array
    {
        $emails = (string)$this->scopeConfig->getValue('punchout/general/test_emails');
        $emails = explode(',', $emails);

        return array_map('trim', $emails);
    }

    public function getCustomerEmailTemplate(): string
    {
        return (string)$this->scopeConfig->getValue('punchout/customer/email_template');
    }
}
