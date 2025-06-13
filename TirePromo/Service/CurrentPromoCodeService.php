<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Tirehub\CssPriceAdjustment\Api\CssPriceCartRepositoryInterface;
use Tirehub\CssPriceAdjustment\Api\Data\CssPriceCartInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;
use Tirehub\TirePromo\Exception\InvalidPromoCodePatternException;
use Tirehub\TirePromo\Exception\LookupPromoCodeException;
use Tirehub\TirePromo\Api\PromoCodeStorageInterface;

class CurrentPromoCodeService implements CurrentPromoCodeManagementInterface
{
    private const PROMO_CODE_REGEX_PATTERN = 'css_price_adjustment/promo_code_validation_rule/validation_pattern';

    public function __construct(
        private readonly PromoCodeStorageInterface $promoCodeStorage,
        private readonly SessionManagerInterface $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly QuoteRepository $quoteRepository,
        private readonly CssPriceCartRepositoryInterface $cssPriceCartRepository,
        private readonly LookupPromoManagement $lookupPromoManagement
    ) {
    }

    /**
     * @throws LookupPromoCodeException
     * @throws InvalidPromoCodePatternException
     */
    public function savePromoCode(string $promoCode): bool
    {
        $promoCode = trim($promoCode);
        $quote = $this->getQuote();
        $isPromoCodePatternValid = $this->validatePromoCodePattern($promoCode);

        if (!$isPromoCodePatternValid || !$quote->getId()) {
            throw new InvalidPromoCodePatternException(__('Promo code invalid. Please try another code.'));
        }

        try {
            $promoData = $this->lookupPromoManagement->getResult($quote, ['promoCode' => $promoCode]);

            if ($promoData['orderLevelDiscount']) {
                $orderLevelDiscount = (float)$promoData['orderLevelDiscount'];
            } else {
                $orderLevelDiscount = 0;
                foreach ($promoData['cartItems'] as $cartItem) {
                    $lineItemDiscount = (float)$cartItem['lineItemDiscount'];
                    if ($lineItemDiscount <= 0) {
                        continue;
                    }

                    $this->setNewPriceValue(
                        $lineItemDiscount,
                        (int)$cartItem['cartLineItemId'],
                        $promoCode,
                        $quote
                    );
                }
                $promoData['orderLevelDiscount'] = $orderLevelDiscount;
            }
            $promoData['orderLevelDiscountPercent'] = $this->calculatePercentageDiscount(
                (float)$quote->getBaseSubtotal(),
                (float)$orderLevelDiscount
            );
            $this->promoCodeStorage->savePromoCode($promoData, (int)$quote->getId());

            $this->quoteRepository->save($quote);
        } catch (LookupPromoCodeException $exception) {
            $this->logger->error($exception->getMessage(), ['promoCode' => $promoCode, 'quoteId' => $quote->getId()]);

            throw $exception;
        }

        return true;
    }

    public function getPromoCode(?Quote $quote = null): ?string
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        if ($quote->getId()) {
            return $this->promoCodeStorage->getPromoCode((int)$quote->getId());
        }

        return null;
    }

    public function isOrderLevel(?Quote $quote = null): bool
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        return $this->promoCodeStorage->isOrderLevel((int) $quote->getId());
    }

    public function isNationWide(?Quote $quote = null): bool
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        return $this->promoCodeStorage->isNationWide((int) $quote->getId());
    }

    public function getAppliedSkus(?Quote $quote = null): ?string
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        return $this->promoCodeStorage->getAppliedSkus((int) $quote->getId());
    }

    public function getDiscountPercent(?Quote $quote = null): ?float
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        if ($quote->getId()) {
            return (float)$this->promoCodeStorage->getDiscountPercent((int)$quote->getId());
        }

        return null;
    }

    public function checkLineItemLevel(?Quote $quote = null): bool
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        return $this->promoCodeStorage->checkLineItemLevel((int)$quote->getId());
    }

    public function getDiscountAmount(?Quote $quote = null): ?float
    {
        if (!$quote) {
            $quote = $this->getQuote();
        }

        if ($quote->getId()) {
            return (float)$this->promoCodeStorage->getDiscountAmount((int)$quote->getId());
        }

        return null;
    }

    public function getOrderSubtotal(): ?float
    {
        $quote = $this->getQuote();

        return (float)$quote->getSubtotal();
    }

    public function getOrderTaxAmount(): ?float
    {
        $quote = $this->getQuote();
        $taxAmount = 0;

        foreach ($quote->getAllVisibleItems() as $item) {
            $taxAmount += $item->getTaxAmount();
        }

        return (float)$taxAmount;
    }

    public function getOrderShippingAmount(): ?float
    {
        $quote = $this->getQuote();
        return (float)$quote->getShippingAddress()->getShippingAmount();
    }

    public function getOrderSpecialAmount(): ?float
    {
        /** @phpstan-ignore-next-line */
        $specialsInfo = $this->checkoutSession->getSpecialProducts();
        return !empty($specialsInfo['saved']) ? (float)$specialsInfo['saved'] : 0;
    }

    public function removePromoCode(): void
    {
        $quote = $this->getQuote();
        $this->promoCodeStorage->removePromoCode((int)$quote->getId());
        $this->quoteRepository->save($quote);
    }

    private function setNewPriceValue(float $discountAmount, int $itemId, string $code, Quote $quote): void
    {
        /** @var CssPriceCartInterface $cssInfo */
        $cssInfo = $this->cssPriceCartRepository->getByItemId($itemId);
        $cssInfo->setQuoteId((int)$quote->getId());

        $item = $quote->getItemById($itemId);
        if (!$item) {
            return;
        }

        $cssInfo->setItemId($itemId);

        $price = $item->getPrice() ?: 0;
        $oldPrice = $cssInfo->getOldPrice() ?: $price;
        $qty = (int)$item->getQty() ?: 0;
        $discountAmount = $qty > 0 ? $discountAmount / $qty : 0;
        $cssInfo->setOldPrice((float)$oldPrice);
        $cssInfo->setNewPrice($oldPrice - $discountAmount);
        $discountPercent = $oldPrice ? ($discountAmount / $oldPrice) * 100 : 0;
        $cssInfo->setDiscountAmount($discountAmount);
        $cssInfo->setDiscountPercent($discountPercent);

        $cssInfo->setPromoCode($code);
        $this->cssPriceCartRepository->save($cssInfo);
    }

    private function calculatePercentageDiscount(float $quoteTotal, float $orderLevelDiscount): float
    {
        if ($orderLevelDiscount > 0) {
            return $orderLevelDiscount / $quoteTotal * 100;
        }

        return 0;
    }

    private function validatePromoCodePattern(?string $promoCode): bool
    {
        if (!$promoCode) {
            return false;
        }
        $pattern = $this->scopeConfig->getValue(self::PROMO_CODE_REGEX_PATTERN);

        return preg_match($pattern, $promoCode) === 1;
    }

    private function getQuote(): Quote
    {
        /** @phpstan-ignore-next-line */
        return $this->checkoutSession->getQuote();
    }
}
