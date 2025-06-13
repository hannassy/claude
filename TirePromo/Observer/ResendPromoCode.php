<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;
use Tirehub\TirePromo\Exception\InvalidPromoCodePatternException;
use Tirehub\TirePromo\Exception\LookupPromoCodeException;

class ResendPromoCode implements ObserverInterface
{
    public function __construct(
        private readonly CurrentPromoCodeManagementInterface $promoCodeService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Item $item */
        $item = $observer->getData('quote_item');
        $promoCode = $this->promoCodeService->getPromoCode();

        $quote = $item->getQuote();
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        if ($promoCode !== null) {
            if (count($quote->getAllItems()) > 0) {
                try {
                    $this->promoCodeService->savePromoCode($promoCode);
                } catch (LookupPromoCodeException | NotFoundException | InvalidPromoCodePatternException $e) {
                    $this->logger->error($e->getMessage(), $e->getTrace());
                    $this->promoCodeService->removePromoCode();
                }
            } else {
                $this->promoCodeService->removePromoCode();
            }
        }
    }
}
