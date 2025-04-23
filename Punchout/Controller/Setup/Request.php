<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Setup;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Service\GetClient;
use Tirehub\Punchout\Model\CxmlProcessor;
use Throwable;

class Request implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly GetClient $getClient,
        private readonly CxmlProcessor $cxmlProcessor,
        private readonly Monolog $logger
    ) {
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $this->logger->info('Punchout: Processing setup request');

            // Log raw content for debugging
            $content = $this->request->getContent();
            $this->logger->debug('Punchout: Raw request content: ' . substr($content, 0, 1000) . (strlen($content) > 1000 ? '...' : ''));

            // Check if content exists
            if (empty($content)) {
                throw new \Exception('Empty request content');
            }

            // Check if content is valid XML
            if (!$this->isValidXml($content)) {
                $this->logger->error('Punchout: Invalid XML format in request');

                $result = $this->rawFactory->create();
                $responseXml = $this->cxmlProcessor->generateInvalidXmlResponse();

                $result->setHeader('Content-Type', 'text/xml');
                $result->setHttpResponseCode(500);
                $result->setContents($responseXml);

                return $result;
            }

            $client = $this->getClient->execute();
            return $client->processRequest($this->request);
        } catch (Throwable $e) {
            $this->logger->error('Punchout: Error processing setup request: ' . $e->getMessage());

            // Return specific error responses based on the error message
            $result = $this->rawFactory->create();
            $result->setHeader('Content-Type', 'text/xml');

            if ($e->getMessage() === 'invalid_identity' || strpos($e->getMessage(), 'find identity') !== false) {
                $responseXml = $this->cxmlProcessor->generateInvalidIdentityResponse();
                $result->setHttpResponseCode(400);
            } elseif ($e->getMessage() === 'invalid_shared_secret' || strpos($e->getMessage(), 'shared secret') !== false) {
                $responseXml = $this->cxmlProcessor->generateInvalidSharedSecretResponse();
                $result->setHttpResponseCode(401);
            } elseif (strpos($e->getMessage(), 'dealer code') !== false || strpos($e->getMessage(), 'address id') !== false) {
                // Extract dealer code if available
                $dealerCode = '';
                if (preg_match('/dealer code: ([^\s]+)/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                }
                $responseXml = $this->cxmlProcessor->generateInvalidDealerCodeResponse($dealerCode);
                $result->setHttpResponseCode(400);
            } elseif (strpos($e->getMessage(), 'not authorized') !== false) {
                // Extract dealer code if available
                $dealerCode = '';
                if (preg_match('/location ([^\s]+)/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                }
                $responseXml = $this->cxmlProcessor->generateUnauthorizedDealerResponse($dealerCode);
                $result->setHttpResponseCode(401);
            } else {
                // Generic error for other cases
                $responseXml = $this->cxmlProcessor->generateInvalidXmlResponse();
                $result->setHttpResponseCode(500);
            }

            $result->setContents($responseXml);
            return $result;
        }
    }

    private function isValidXml(string $content): bool
    {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }

        // Remove UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Remove any leading whitespace before XML declaration
        $content = preg_replace('/^[\s\r\n]+/', '', $content);

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return $doc !== false && empty($errors);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
