<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Api\GetTempPoInterface;
use Tirehub\Punchout\Model\ResourceModel\Session\CollectionFactory as SessionCollectionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Psr\Log\LoggerInterface;

class GetTempPo implements GetTempPoInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly SessionCollectionFactory $sessionCollectionFactory,
        private readonly SessionResource $sessionResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): string
    {
        try {
            // Get customer ID
            $customerId = (int)$this->customerSession->getCustomerId();
            if (!$customerId) {
                return '';
            }

            // Find active punchout session for customer
            $collection = $this->sessionCollectionFactory->create();
            $collection->addFieldToFilter(SessionInterface::CUSTOMER_ID, $customerId);
            $collection->addFieldToFilter(SessionInterface::STATUS, SessionInterface::STATUS_ACTIVE);
            $collection->setOrder('updated_at', 'DESC');
            $collection->setPageSize(1);

            $session = $collection->getFirstItem();
            if (!$session || !$session->getId()) {
                return '';
            }

            // Generate unique temppo
            $temppo = self::TEMPPPO_PREFIX . substr(hash('sha256', $session->getId()), 0, 38);

            // Update session
            $session->setData(SessionInterface::TEMPPO, $temppo);
            $this->sessionResource->save($session);

            $this->logger->info("Punchout: Updated session {$session->getId()} with temppo: {$temppo}");

            return $temppo;
        } catch (LocalizedException $e) {
            $this->logger->error("Punchout: Error updating temppo: {$e->getMessage()}");
            return '';
        } catch (\Exception $e) {
            $this->logger->error("Punchout: Unexpected error updating temppo: {$e->getMessage()}");
            return '';
        }
    }
}
