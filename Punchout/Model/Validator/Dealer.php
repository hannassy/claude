<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Tirehub\ApiMiddleware\Api\Request\LookupDealersInterface;

class Dealer
{
    public function __construct(
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly LookupDealersInterface $lookupDealers
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(string $dealerCode, string $identity): void
    {
        $result = $this->getPunchoutPartnersManagement->getResult();
        $partner = [];

        foreach ($result as $item) {
            $itemDomain = strtolower($item['identity'] ?? '');
            if ($itemDomain === strtolower($identity)) {
                $partner = $item;
                break;
            }
        }

        if (!$partner) {
            throw new LocalizedException(__('Unable to find identity match!'));
        }

        $dealerPrefix = $partner['dealerPrefix'] ?? '';
        $dealerCode = str_replace($dealerPrefix, '', $dealerCode);

        $result = $this->lookupDealers->execute(['dealerCode' => $dealerCode]);
        $resultDealerCode = $result['results'][0]['shipToLocation']['locationId'] ?? null;

        if (!$resultDealerCode) {
            throw new LocalizedException(
                __('Unable to match requested address id %1 to TireHub Ship To! Please contact your administrator', $dealerCode)
            );
        }
    }
}
