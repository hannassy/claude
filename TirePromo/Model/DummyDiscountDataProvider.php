<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model;

use Tirehub\CssPriceAdjustment\Api\DiscountDataProviderInterface;

class DummyDiscountDataProvider implements DiscountDataProviderInterface
{
    private const LINE_LEVEL_PROMO_CODE = "LINE123";
    private const ORDER_LEVEL_PROMO_CODE = "ORDER123";

    public function getDiscountData(string $promoCode): array
    {
        $result = [];

        if (self::LINE_LEVEL_PROMO_CODE === $promoCode) {
            $result = [
                "cartLevelDiscount" => 0,
                "orderLinesDiscount" => [
                    ["id" => 1, "discount" => 10],
                    ["id" => 2, "discount" => 20],
                ],
                "shipToId" => "",
                "promoCode" => "",
                "dealerCode" => ""
            ];
        } elseif (self::ORDER_LEVEL_PROMO_CODE === $promoCode) {
            $result = [
                "cartLevelDiscount" => 10,
                "orderLinesDiscount" => [],
                "shipToId" => "",
                "promoCode" => "",
                "dealerCode" => ""
            ];
        }

        return $result;
    }
}
