<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tirehub\TirePromo\Api\LogRepositoryInterface;
use Tirehub\TirePromo\Model\LogFactory;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class LogPromoCodeUsage implements ObserverInterface
{
    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
        private readonly LogFactory $logFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();

            if (!$order instanceof Order) {
                return;
            }

            $quoteId = $order->getQuoteId();
            $quote = $this->cartRepository->get($quoteId);

            $couponCode = $quote->getCouponCode();
            if (!$couponCode) {
                return;
            }

            $shipTo = $this->getShipToLocation($order);

            foreach ($order->getAllVisibleItems() as $item) {
                $log = $this->logFactory->create();

                $log->setPromoCode($couponCode);
                $log->setShipTo($shipTo);
                $log->setSku($item->getSku());
                $log->setBrandPattern($item->getName());
                $log->setQty((int)$item->getQtyOrdered());

                $discountAmount = abs((float)$item->getDiscountAmount());
                $log->setDiscountTotal($discountAmount);

                $log->setOrderId((int)$order->getId());
                $log->setQuoteId((int)$quoteId);
                $log->setCustomerId($order->getCustomerId() ? (int)$order->getCustomerId() : null);

                $this->logRepository->save($log);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error logging promo code usage: ' . $e->getMessage());
        }
    }

    private function getShipToLocation(Order $order): ?string
    {
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress) {
            $erpAddressCode = $shippingAddress->getData('erp_erp_address_code');
            if ($erpAddressCode) {
                return $erpAddressCode;
            }
        }

        $customer = $order->getCustomer();
        if ($customer) {
            $erpStore = $customer->getCustomAttribute('erp_store');
            if ($erpStore) {
                return $erpStore->getValue();
            }
        }

        return null;
    }
}
