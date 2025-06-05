<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Plugin\Checkout;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Silk\Checkout\Model\PaymentInformationManagement;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;
use Tirehub\Punchout\Model\Process\PlaceOrder as PlaceOrderProcess;
use Magento\Sales\Api\OrderRepositoryInterface;

class SavePaymentInformationAndPlaceOrder
{
    public function __construct(
        private readonly IsPunchoutModeInterface $isPunchoutMode,
        private readonly PlaceOrderProcess $placeOrderProcess,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function afterSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        $result,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ) {
        if (!$this->isPunchoutMode->execute()) {
            return $result;
        }

        $order = $this->orderRepository->get($result);

        return $this->placeOrderProcess->execute($order);
    }
}
