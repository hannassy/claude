<?php

namespace Tirehub\TirePromo\Controller\Ajax;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;

class RemovePromoCode implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CurrentPromoCodeManagementInterface $promoCodeManagement,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        /** @phpstan-ignore-next-line  */
        if (!$this->request->isXmlHttpRequest()) {
            return $this->jsonFactory->create()->setData($result);
        }

        try {
            $this->promoCodeManagement->removePromoCode();
            $result->setData(
                [
                    "error" => false,
                    "message" => ''
                ]
            );
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
            $result = $result->setData(
                [
                    'error' => true,
                    'message' => __('Promo code could not be removed')
                ]
            );
            $this->messageManager->addErrorMessage(__('Promo code could not be removed. Please try again.')->render());
        }

        return $result;
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
