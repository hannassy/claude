<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    public function getConfig(): array
    {
        return [
            'isPunchoutMode' => $this->isPunchoutMode->execute()
        ];
    }
}
