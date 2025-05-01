<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Framework\Exception\LocalizedException;
use Tirehub\ApiMiddleware\Api\Request\LookupDealersInterface;
use Exception;
use Psr\Log\LoggerInterface;

class ExtractAddressId
{
    public function __construct(
        private readonly LookupDealersInterface $lookupDealers,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(string $addressId, string $senderIdentity): ?string
    {
        $result = $this->getPunchoutPartnersManagement->getResult();
        $partner = [];
        $senderIdentity = strtolower($senderIdentity);

        foreach ($result as $item) {
            $identity = strtolower($item['identity'] ?? '');
            if ($identity === strtolower($senderIdentity)) {
                $partner = $item;
                break;
            }
        }

        $formattedAddressId = $addressId;
        $trimLeadingZeroFromDealerCode = $partner['trimLeadingZeroFromDealerCode'] ?? false;
        if ($trimLeadingZeroFromDealerCode
            && strlen($addressId) >= 5
            && str_starts_with($addressId, '0')
        ) {
            $formattedAddressId = substr($addressId, 1, 4);
        }

        // Special formatting for CarMax
        if ($senderIdentity === 'carmax') {
            // For CarMax: use only 4 characters if address is greater then 6 characters start from 2nd
            if (strlen($addressId) >= 6) {
                $formattedAddressId = substr($addressId, 1, 4);
            }
        }

        $dealerPrefix = $partner['dealerPrefix'] ?? '';
        $formattedAddressId = str_replace($dealerPrefix, '', $formattedAddressId);

        // Apply dealer prefix if configured
        if ($dealerPrefix) {
            $formattedAddressId = $dealerPrefix . $formattedAddressId;
        }

        $this->logger->info("Punchout: Formatted addressID from '{$addressId}' to '{$formattedAddressId}'");

        try {
            $result = $this->lookupDealers->execute(['dealerCode' => $formattedAddressId]);
            $resultDealerCode = $result['results'][0]['shipToLocation']['locationId'] ?? null;

            if (!$resultDealerCode) {
                throw new LocalizedException(__('Dealer with addressId="%1" was not found', $formattedAddressId));
            }
        } catch (Exception $e) {
            $this->logger->error('Punchout: ' . $e->getMessage());
            $resultDealerCode = null;
        }

        return $resultDealerCode;
    }
}
