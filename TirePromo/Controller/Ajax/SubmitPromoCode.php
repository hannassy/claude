<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;
use Tirehub\TirePromo\Exception\InvalidPromoCodePatternException;
use Tirehub\TirePromo\Exception\LookupPromoCodeException;

class SubmitPromoCode implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request,
        private readonly CurrentPromoCodeManagementInterface $promoCodeService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        /** @phpstan-ignore-next-line  */
        if (!$this->request->isXmlHttpRequest()) {
            $result->setData([
                "error" => true,
                "valid" => false,
                "message" => __('Wrong type of request.')->render()
            ]);

            return $result;
        }

        $promoCode = $this->request->getParam('promoCode');

        if (!$promoCode) {
            $result->setData([
                "error" => false,
                "valid" => false,
                "message" => __('Promo Code not set.')->render()
            ]);

            return $result;
        }
        $response = [
            "error" => false,
            "valid" => true,
            "message" => __('Promo Code successfully applied.')->render()
        ];

        try {
            $isApplied = $this->promoCodeService->savePromoCode($promoCode);
            if ($isApplied) {
                $isNationWide = $this->promoCodeService->isNationWide();
                $appliedSkus = $this->promoCodeService->getAppliedSkus();
                if (!$isNationWide && $appliedSkus) {
                    $response['primaryOnlyItems'] = explode(',', (string)$appliedSkus);
                }
            }
        } catch (LookupPromoCodeException|InvalidPromoCodePatternException $e) {
            $this->logger->error($e->getMessage());
            $response['error'] = true;
            $response['message'] = __($e->getMessage())->render();
        } catch (NotFoundException $e) {
            $this->logger->error($e->getMessage(), ['promoCode' => $promoCode]);
            $response['message'] = __('There has been an error. Please try again')->render();
            $response['error'] = true;
        }

        $result->setData($response);

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
