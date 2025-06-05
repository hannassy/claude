<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Plugin\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Silk\Checkout\Model\Relation;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class PreventSplitOrder
{
    public function __construct(
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

        return [$quote];
    }
}
