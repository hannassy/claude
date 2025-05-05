<?php
declare(strict_types=1);

namespace Tirehub\Punchout\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Tirehub\Punchout\Api\IsPunchoutModeInterface;

class Punchout implements ArgumentInterface
{
    public function __construct(
        private readonly IsPunchoutModeInterface $isPunchoutMode
    ) {
    }

    public function isPunchoutMode(): bool
    {
        return $this->isPunchoutMode->execute();
    }
}
