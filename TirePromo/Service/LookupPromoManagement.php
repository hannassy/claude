<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Service;

use Magento\Quote\Model\Quote;
use Tirehub\ApiMiddleware\Api\Request\LookupPromoInterface;
use Tirehub\Company\Api\GetDealerCodeServiceInterface;
use Tirehub\TirePromo\Exception\LookupPromoCodeException;
use Tirehub\Utility\Api\CacheManagementInterface;

class LookupPromoManagement
{
    public function __construct(
        private readonly LookupPromoInterface $lookupPromo,
        private readonly CacheManagementInterface $cacheManagement,
        private readonly GetDealerCodeServiceInterface $getDealerCodeService,
        private readonly string $cacheId = '',
        private readonly int $cacheLifetime = 0
    ) {
    }

    /**
     * @throws LookupPromoCodeException
     */
    public function getResult(Quote $quote, array $params): array
    {
        $params = $this->getMergedWithDefault($quote, $params);
        $data = $this->cacheManagement->get($this->cacheId);
        if (!$data) {
            $data = $this->lookupPromo->execute($params);
            $this->cacheManagement->save($this->cacheId, $this->cacheLifetime, $data);
        }

        if (isset($data['successfullyApplied']) && $data['successfullyApplied'] === false) {
            throw new LookupPromoCodeException(__($data['failureMessage']) ?? 'Cannot apply promo code.');
        }

        return $data;
    }

    private function getMergedWithDefault(Quote $quote, array $params = []): array
    {
        $params['dealerCode'] = $this->getDealerCodeService->execute();
        $params['cartItems'] = $this->getCartItems($quote);

        return $params;
    }

    private function getCartItems(Quote $quote): array
    {
        $result = [];

        foreach ($quote->getAllItems() as $item) {
            $result[] = [
                'cartLineItemId' => $item->getId(),
                'itemId' => $item->getProduct()->getSku(),
                'quantity' => $item->getQty(),
                'warehouseId' => $item->getData('thn_used_location')
            ];
        }

        return $result;
    }
}
