<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Setup;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Tirehub\Punchout\Service\GetClient;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultInterface;

class Item implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly GetClient $getClient
    ) {
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $client = $this->getClient->execute();
        return $client->processItem($this->request);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
