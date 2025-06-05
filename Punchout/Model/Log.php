<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Tirehub\Punchout\Api\Data\LogInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\ResourceModel\Log as ResourceModel;

class Log extends AbstractModel implements LogInterface
{
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly CustomerSession $customerSession,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        private readonly SessionFactory $sessionFactory,
        ResourceModel $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function log(string $level, string $message, array $context = [], ?string $buyerCookie = null): void
    {
        try {
            $sessionId = $this->getSessionId($buyerCookie);

            if (!$sessionId) {
                $this->logger->debug('Punchout: Cannot log without session ID', ['message' => $message]);
                return;
            }

            $this->setData([
                self::SESSION_ID => $sessionId,
                self::LEVEL => $level,
                self::MESSAGE => $this->truncateMessage($message),
                self::CONTEXT => !empty($context) ? $this->serializer->serialize($context) : null,
                self::SOURCE => $this->getSource()
            ]);

            $this->getResource()->save($this);
            $this->setData([]); // Clear data after save for reuse
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Failed to save log entry: ' . $e->getMessage());
        }
    }

    public function logDebug(string $message, array $context = [], ?string $buyerCookie = null): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context, $buyerCookie);
    }

    public function logInfo(string $message, array $context = [], ?string $buyerCookie = null): void
    {
        $this->log(self::LEVEL_INFO, $message, $context, $buyerCookie);
    }

    public function logWarning(string $message, array $context = [], ?string $buyerCookie = null): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context, $buyerCookie);
    }

    public function logError(string $message, array $context = [], ?string $buyerCookie = null): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context, $buyerCookie);
    }

    public function logCritical(string $message, array $context = [], ?string $buyerCookie = null): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context, $buyerCookie);
    }

    public function logForSession(int $sessionId, string $level, string $message, array $context = []): void
    {
        try {
            $this->setData([
                self::SESSION_ID => $sessionId,
                self::LEVEL => $level,
                self::MESSAGE => $this->truncateMessage($message),
                self::CONTEXT => !empty($context) ? $this->serializer->serialize($context) : null,
                self::SOURCE => $this->getSource()
            ]);

            $this->getResource()->save($this);
            $this->setData([]); // Clear data after save for reuse
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Failed to save log entry: ' . $e->getMessage());
        }
    }

    private function getSessionId(?string $buyerCookie = null): ?int
    {
        if (!$buyerCookie) {
            $buyerCookie = $this->customerSession->getData('buyer_cookie');
        }

        if (!$buyerCookie) {
            return null;
        }

        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

        return $session->getId() ? (int)$session->getId() : null;
    }

    private function getSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        foreach ($trace as $frame) {
            if (isset($frame['class']) &&
                strpos($frame['class'], 'Tirehub\\Punchout\\') === 0 &&
                $frame['class'] !== self::class) {
                return $frame['class'] . '::' . ($frame['function'] ?? 'unknown');
            }
        }

        return 'Unknown';
    }

    private function truncateMessage(string $message, int $maxLength = 1024): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }
}
