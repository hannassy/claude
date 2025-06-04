<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;

class DisplayMessages
{
    private const SESSION_KEY = 'punchout_deferred_messages';

    public function __construct(
        private readonly SessionManagerInterface $session,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): void
    {
        $messages = $this->session->getData(self::SESSION_KEY, true);

        if (empty($messages)) {
            return;
        }

        $message = $messages[0]['text'] ?? '';
        $messageType = $messages[0]['type'] ?? 'error';

        if ($message) {
            if ($messageType == 'error') {
                $this->messageManager->addErrorMessage($message);
            }
        }
    }
}
