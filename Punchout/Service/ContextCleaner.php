<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Company\Model\CompanyContext;
use Psr\Log\LoggerInterface;

class ContextCleaner
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly HttpContext $httpContext,
        private readonly CompanyContext $companyContext,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Punchout: Cleaning HTTP context and session');

        // Clear customer session
        if ($this->customerSession->isLoggedIn()) {
            $this->customerSession->logout();
        }
        $this->customerSession->clearStorage();
        $this->customerSession->regenerateId();

        // Clear HTTP context
        $this->httpContext->setValue(CustomerContext::CONTEXT_AUTH, false, false);
        $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, 0, 0);
        $this->httpContext->setValue('company_id', null, null);
        $this->httpContext->setValue('customer_id', null, null);
        $this->httpContext->setValue('customer_logged_in', false, false);

        // Clear any custom context values that might exist
        $contextData = $this->httpContext->getData();
        foreach ($contextData as $key => $value) {
            if (!in_array($key, ['store', 'currency'])) {
                $this->httpContext->unsValue($key);
            }
        }

        // Force company context to clear its cache
        if (method_exists($this->companyContext, 'setCustomerId')) {
            $this->companyContext->setCustomerId(null);
        }

        $this->logger->info('Punchout: HTTP context and session cleaned');
    }
}
