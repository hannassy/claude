<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;

class ClearSession implements ObserverInterface
{
    public function __construct(
        private readonly DisablePunchoutModeInterface $disablePunchoutMode
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->disablePunchoutMode->execute();
    }
}
