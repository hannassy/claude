<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Plugin\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Silk\Checkout\Model\Checkout as SilkCheckout;
use Silk\Checkout\Model\Relation;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class PreventSplitOrder
{
    public function __construct(
        private readonly SilkCheckout $silkCheckout,
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function aroundSplit(Relation $subject, callable $proceed, Quote $quote): array
    {
        if (!$this->isPunchoutMode->execute()) {
            return $proceed($quote);
        }

        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $extensionAttributes = $quoteItem->getExtensionAttributes();
            $noShippingMethod = false;
            if ($extensionAttributes) {
                $shippingExtension = $extensionAttributes->getThnShipping();

                if ($shippingExtension && $shippingExtension->getMethod()) {
                    if (!isset($shippings[$shippingExtension->getMethod()])) {
                        $shippings[$shippingExtension->getMethod()] = ['ids' => [], 'items' => []];
                    }

                    $shippings[$shippingExtension->getMethod()]['ids'][] = $quoteItem->getId();
                    $shippings[$shippingExtension->getMethod()]['items'][] = $quoteItem;
                } else {
                    $noShippingMethod = true;
                }
            } else {
                $noShippingMethod = true;
            }

            if ($noShippingMethod) {
                throw new LocalizedException(
                    __('Please select shipping method for all items on shopping cart: SKU - %1, QTY - %2, warehouse - %3, location code - %4',
                        $quoteItem->getSku(),
                        $quoteItem->getQty(),
                        $quoteItem->getThnLocationName(),
                        $quoteItem->getThnUsedLocation()
                    )
                );
            }
        }

        $code = false;
        // TODO we need one shipping per quote only
        foreach ($shippings as $shippingMethod => $shipping) {
            $code = $shippingMethod;
            break;
        }

        if ($code) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->getShippingAddress()->setShippingMethod($code);
            $quote->setTotalsCollectedFlag(false);
            $this->silkCheckout->prepareShippingAssignment($quote);
        }

        return [$quote];
    }
}
