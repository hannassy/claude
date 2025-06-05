<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Company\Model\CompanyContext;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\App\PageCache\Cache;

class ContextCleaner
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly HttpContext $httpContext,
        private readonly CompanyContext $companyContext,
        private readonly LoggerInterface $logger,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionManagerInterface $sessionManager,
        private readonly Version $pageCacheVersion,
        private readonly Cache $pageCacheCache
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Punchout: Starting comprehensive context and session cleaning');

        // Clear customer session first
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            $this->logger->info("Punchout: Logging out customer ID: {$customerId}");
            $this->customerSession->logout();
        }

        // Clear and regenerate session
        $this->customerSession->clearStorage();
        $this->customerSession->regenerateId();
        $this->sessionManager->regenerateId();

        // Clear HTTP context
        $this->httpContext->setValue(CustomerContext::CONTEXT_AUTH, false, false);
        $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, 0, 0);
        $this->httpContext->setValue('company_id', null, null);
        $this->httpContext->setValue('customer_id', null, null);
        $this->httpContext->setValue('customer_logged_in', false, false);

        // Clear any custom context values
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

        // Clear cookies that affect customer sections
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration(0)
            ->setPath('/')
            ->setHttpOnly(false);

        $cookiesToClear = [
            'private_content_version',
            'section_data_ids',
            'mage-cache-sessid',
            'mage-cache-storage',
            'mage-messages',
            'product_data_storage'
        ];

        foreach ($cookiesToClear as $cookieName) {
            try {
                $this->cookieManager->deleteCookie($cookieName, $metadata);
            } catch (\Exception $e) {
                $this->logger->warning("Punchout: Could not delete cookie {$cookieName}: " . $e->getMessage());
            }
        }

        // Force new page cache version
        try {
            $this->pageCacheVersion->process();
        } catch (\Exception $e) {
            $this->logger->warning('Punchout: Could not update page cache version: ' . $e->getMessage());
        }

        // Clear full page cache for customer-specific pages
        try {
            $this->pageCacheCache->clean();
        } catch (\Exception $e) {
            $this->logger->warning('Punchout: Could not clean page cache: ' . $e->getMessage());
        }

        $this->logger->info('Punchout: Context and session cleaning completed');
    }
}
