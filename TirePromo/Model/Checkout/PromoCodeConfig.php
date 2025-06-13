<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Tirehub\CssPriceAdjustment\Api\ConstantsInterface;
use Tirehub\CssPriceAdjustment\Api\CssRoleMetadataInterface;
use Tirehub\CssPriceAdjustment\Api\GetCurrentUserCssRoleInterface;
use Tirehub\TirePromo\Api\CurrentPromoCodeManagementInterface;
use Tirehub\Specials\Service\LookupDiscountsManagement;

class PromoCodeConfig implements ConfigProviderInterface
{
    private ?string $role = null;

    public function __construct(
        private readonly UrlInterface $url,
        private readonly CurrentPromoCodeManagementInterface $currentPromoCodeManagement,
        private readonly GetCurrentUserCssRoleInterface $getUserCssRole,
        private readonly LookupDiscountsManagement $lookupDiscountsManagement
    ) {
    }

    public function getConfig(): array
    {
        $promoCode = $this->currentPromoCodeManagement->getPromoCode();

        return [
            'promoCode' => [
                'promoCode' => $promoCode,
                'hasCheckoutPromo' => $this->hasCheckoutPromo(),
                'submitPromoCodeUrl' => $this->url->getUrl(ConstantsInterface::CSS_SUBMIT_PROMO_CODE_URL),
                'removePromoCodeUrl' => $this->url->getUrl(ConstantsInterface::CSS_REMOVE_PROMO_CODE_URL),
                'resetCartPricingUrl' => $this->url->getUrl(ConstantsInterface::CSS_RESET_CART_PRICING_URL),
                'is_government' => (int)$this->isGovernment(),
                'primaryOnlyItems' => $this->getPrimaryOnlyItems($promoCode)
            ]
        ];
    }

    private function isGovernment(): bool
    {
        return $this->getRole() === CssRoleMetadataInterface::CSS_ROLE_ADMINISTRATOR;
    }

    private function getRole(): ?string
    {
        if ($this->role === null) {
            $this->role = $this->getUserCssRole->execute();
        }

        return $this->role;
    }

    private function getPrimaryOnlyItems(?string $promoCode): array
    {
        if (!$promoCode) {
            return [];
        }

        $isNationWide = $this->currentPromoCodeManagement->isNationWide();
        if ($isNationWide) {
            return [];
        }

        $appliedSkus = (string)$this->currentPromoCodeManagement->getAppliedSkus();

        return explode(',', $appliedSkus);
    }

    private function hasCheckoutPromo():bool
    {
        $result = $this->lookupDiscountsManagement->getResult();

        return $result['has_checkout_promo'] ?? false;
    }
}
