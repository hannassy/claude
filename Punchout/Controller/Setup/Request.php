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
use Tirehub\Punchout\Model\Process\Request as RequestProcess;
use Tirehub\Punchout\Model\CxmlProcessor;
use Throwable;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Tirehub\Punchout\Service\ContextCleaner;

class Request implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly RequestProcess $requestProcess,
        private readonly CxmlProcessor $cxmlProcessor,
        private readonly Monolog $logger,
        private readonly DisablePunchoutModeInterface $disablePunchoutMode,
        private readonly ContextCleaner $contextCleaner
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

            $this->disablePunchoutMode->execute();
            $this->contextCleaner->execute();

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

            return $this->requestProcess->execute($this->request);
        } catch (Throwable $e) {
            $this->logger->error('Punchout: Error processing setup request: ' . $e->getMessage());

            // Return specific error responses based on the error message
            $result = $this->rawFactory->create();
            $result->setHeader('Content-Type', 'text/xml');

            if ($e->getMessage() === 'invalid_identity'
                || str_contains($e->getMessage(), 'find identity')
                || str_contains($e->getMessage(), 'Partner not found')
            ) {
                $responseXml = $this->cxmlProcessor->generateInvalidIdentityResponse();
                $result->setHttpResponseCode(400);
            } elseif ($e->getMessage() === 'invalid_shared_secret'
                || str_contains($e->getMessage(), 'shared secret')
            ) {
                $responseXml = $this->cxmlProcessor->generateInvalidSharedSecretResponse();
                $result->setHttpResponseCode(401);
            } elseif (str_contains($e->getMessage(), 'dealer code')
                || str_contains($e->getMessage(), 'address id')
                || str_contains($e->getMessage(), 'Dealer not found')
                || str_contains($e->getMessage(), 'Invalid dealer code')
            ) {
                $dealerCode = '';
                if (preg_match('/dealer code: ([^\s]+)/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                } elseif (preg_match('/address id ([^\s]+)/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                } elseif (preg_match('/addressId=\'([^\']+)\'/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                }
                $responseXml = $this->cxmlProcessor->generateInvalidDealerCodeResponse($dealerCode);
                $result->setHttpResponseCode(400);
            } elseif (str_contains($e->getMessage(), 'not authorized')
                || str_contains($e->getMessage(), 'not currently authorized')
            ) {
                $dealerCode = '';
                if (preg_match('/location ([^\s]+)/i', $e->getMessage(), $matches)) {
                    $dealerCode = $matches[1];
                }
                $responseXml = $this->cxmlProcessor->generateUnauthorizedDealerResponse($dealerCode);
                $result->setHttpResponseCode(401);
            } else {
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
