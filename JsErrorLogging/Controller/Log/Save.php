<?php

declare(strict_types=1);

namespace Tirehub\JsErrorLogging\Controller\Log;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Tirehub\JsErrorLogging\Model\JsErrorFactory;
use Psr\Log\LoggerInterface;

class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly JsErrorFactory $jsErrorFactory,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->request->isPost()) {
            return $result->setData(['success' => false, 'message' => 'Invalid request']);
        }

        try {
            $data = $this->request->getContent();
            $errorData = json_decode($data, true);

            if (!$errorData || !isset($errorData['message'])) {
                return $result->setData(['success' => false, 'message' => 'Invalid data']);
            }

            $jsError = $this->jsErrorFactory->create();

            $jsError->setData([
                'error_message' => $this->truncate($errorData['message'] ?? '', 500),
                'stack_trace' => $errorData['stack'] ?? null,
                'source_file' => $this->truncate($errorData['source'] ?? '', 500),
                'line_number' => isset($errorData['lineno']) ? (int)$errorData['lineno'] : null,
                'column_number' => isset($errorData['colno']) ? (int)$errorData['colno'] : null,
                'page_url' => $this->truncate($errorData['url'] ?? '', 500),
                'user_agent' => $this->truncate($errorData['userAgent'] ?? '', 500),
                'browser_name' => $this->truncate($errorData['browser'] ?? '', 100),
                'browser_version' => $this->truncate($errorData['browserVersion'] ?? '', 50),
                'os_name' => $this->truncate($errorData['os'] ?? '', 100),
                'device_type' => $this->truncate($errorData['deviceType'] ?? '', 50),
                'customer_id' => $this->customerSession->getCustomerId(),
                'session_id' => $this->customerSession->getSessionId(),
                'quote_id' => $this->checkoutSession->getQuoteId(),
                'ip_address' => $this->remoteAddress->getRemoteAddress(),
                'viewport_width' => isset($errorData['viewportWidth']) ? (int)$errorData['viewportWidth'] : null,
                'viewport_height' => isset($errorData['viewportHeight']) ? (int)$errorData['viewportHeight'] : null
            ]);

            $jsError->save();

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('JS Error Logging failed: ' . $e->getMessage());
            return $result->setData(['success' => false, 'message' => 'Failed to log error']);
        }
    }

    private function truncate(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}