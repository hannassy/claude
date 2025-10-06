<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Api\GetCustomerReferenceNumberInterface;
use Tirehub\Punchout\Model\ResourceModel\Session\CollectionFactory as SessionCollectionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Psr\Log\LoggerInterface;

class GetCustomerReferenceNumber implements GetCustomerReferenceNumberInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly SessionCollectionFactory $sessionCollectionFactory,
        private readonly SessionResource $sessionResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(OrderInterface $order): string
    {
        try {
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

            $payloadId = md5($session->getId() . uniqid()) . '@tirehub';

            // Update session
            $session->setData(SessionInterface::PAYLOAD_ID, $payloadId);
            $this->sessionResource->save($session);

            $this->logger->info("Punchout: Updated session {$session->getId()} with payloadId: {$payloadId}");

            $data = ';Punchout=true;';
            $data .= 'Payload=' . $payloadId . ';';
            $data .= 'RequesterEmail=' . $order->getCustomerEmail() .';';

            return $data;
        } catch (LocalizedException $e) {
            $this->logger->error("Punchout: Error updating payloadId: {$e->getMessage()}");
            return '';
        } catch (\Exception $e) {
            $this->logger->error("Punchout: Unexpected error updating payloadId: {$e->getMessage()}");
            return '';
        }
    }
}
