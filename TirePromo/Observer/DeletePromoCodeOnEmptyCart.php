<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;

class DeletePromoCodeOnEmptyCart implements ObserverInterface
{
    public function __construct(
        private readonly CurrentPromoCodeManagementInterface $promoCodeService,
        private readonly RequestInterface $request
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function execute(Observer $observer): void
    {
        $promoCode = $this->promoCodeService->getPromoCode();
        $updateAction = (string) $this->request->getParam('update_cart_action');

        if ($promoCode && $updateAction === 'empty_cart') {
            $this->promoCodeService->removePromoCode();
        }
    }
}
