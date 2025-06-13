<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Block;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tirehub\Specials\Service\LookupDiscountsManagement;

class Promo extends Template
{
    public function __construct(
        Context $context,
        private readonly LookupDiscountsManagement $lookupDiscountsManagement,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCustomerPromoItems(): string
    {
        $result = $this->getResult();
        $result = $result['customer_promo'] ?? [];

        return (string)$this->serializer->serialize($result);
    }

    private function getResult(): array
    {
        return $this->lookupDiscountsManagement->getResult();
    }
}
