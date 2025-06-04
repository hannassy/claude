<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Plugin\Checkout\Index;

use Magento\Checkout\Controller\Index\Index;
use Tirehub\Punchout\Service\DisplayMessages as DisplayMessagesService;

class DisplayMessages
{
    public function __construct(
        private readonly DisplayMessagesService $messageService
    ) {
    }

    public function beforeExecute(Index $subject): void
    {
        $this->messageService->execute();
    }
}
