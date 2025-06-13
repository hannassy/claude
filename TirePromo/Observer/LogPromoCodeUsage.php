<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tirehub\TirePromo\Api\LogRepositoryInterface;
use Tirehub\TirePromo\Model\LogFactory;
use Psr\Log\LoggerInterface;
use Tirehub\Company\Api\GetDealerCodeServiceInterface;

class LogPromoCodeUsage implements ObserverInterface
{
    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
        private readonly LogFactory $logFactory,
        private readonly LoggerInterface $logger,
        private readonly GetDealerCodeServiceInterface $getDealerCodeService
    ) {
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getData('order');
            $result = $observer->getData('result');

            if (!$order || !$order->getId() || !$result) {
                return;
            }

            $erpOrderNumber = $result['orderNo'] ?? null;
            $couponCode = $order->getCouponCode();

            if (!$couponCode || !$erpOrderNumber) {
                return;
            }

            $shipTo = $this->getDealerCodeService->execute();

            foreach ($order->getAllVisibleItems() as $item) {
                $log = $this->logFactory->create();

                $log->setPromoCode($couponCode);
                $log->setShipTo($shipTo);
                $log->setSku($item->getSku());
                $log->setBrandPattern($item->getName());
                $log->setQty((int)$item->getQtyOrdered());

                $discountAmount = abs((float)$item->getDiscountAmount());
                $log->setDiscountTotal($discountAmount);

                $log->setOrderId((int)$erpOrderNumber);
                $log->setCustomerId($order->getCustomerId() ? (int)$order->getCustomerId() : null);

                $this->logRepository->save($log);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error logging promo code usage: ' . $e->getMessage());
        }
    }
}
