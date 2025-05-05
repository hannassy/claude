<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Framework\Exception\LocalizedException;
use Tirehub\ApiMiddleware\Api\Request\LookupDealersInterface;
use Tirehub\ApiMiddleware\Api\Request\LookupCommonDealersInterface;
use Exception;
use Psr\Log\LoggerInterface;

class ExtractAddressId
{
    public function __construct(
        private readonly LookupDealersInterface $lookupDealers,
        private readonly LookupCommonDealersInterface $lookupCommonDealers,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Extract and validate addressId from cXML request
     *
     * @param string $addressId Raw addressId from cXML
     * @param string $senderIdentity Partner identity
     * @return string|null Valid dealerCode or null if invalid
     */
    public function execute(string $addressId, string $senderIdentity): ?string
    {
        try {
            // Get partner configuration
            $partner = $this->getPartnerConfiguration($senderIdentity);
            if (!$partner) {
                $this->logger->error("Punchout: Partner not found with identity: {$senderIdentity}");
                return null;
            }

            // Format address ID based on partner rules
            $formattedAddressId = $this->formatAddressId($addressId, $partner, $senderIdentity);
            $this->logger->info("Punchout: Formatted addressID from '{$addressId}' to '{$formattedAddressId}'");

            // Get corpAddressId for partner
            $corpAddressId = $partner['corpAddressId'] ?? null;
            if (!$corpAddressId) {
                $this->logger->error("Punchout: Missing corpAddressId for partner: {$senderIdentity}");
                return null;
            }

            // Validate the address exists in dealer lookup
            $validDealerCode = $this->getValidDealerCode($formattedAddressId);
            if (!$validDealerCode) {
                $this->logger->error("Punchout: Dealer with addressId='{$formattedAddressId}' not found in lookupDealers");
                return null;
            }

            // Validate the address belongs to this partner's common dealers
            $isAssociatedWithPartner = $this->validateAddressAssociatedWithPartner($validDealerCode, $corpAddressId);
            if (!$isAssociatedWithPartner) {
                $this->logger->error("Punchout: validDealerCode='{$validDealerCode}' is not associated with partner's corpAddressId='{$corpAddressId}'");
                return null;
            }

            return $validDealerCode;
        } catch (Exception $e) {
            $this->logger->error('Punchout: Error extracting addressId: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get partner configuration from GetPunchoutPartnersManagement service
     *
     * @param string $senderIdentity Partner identity
     * @return array|null Partner configuration or null if not found
     */
    private function getPartnerConfiguration(string $senderIdentity): ?array
    {
        $result = $this->getPunchoutPartnersManagement->getResult();
        $senderIdentity = strtolower($senderIdentity);

        foreach ($result as $item) {
            $identity = strtolower($item['identity'] ?? '');
            if ($identity === $senderIdentity) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Format the addressId based on partner-specific rules
     *
     * @param string $addressId Raw addressId
     * @param array $partner Partner configuration
     * @param string $senderIdentity Partner identity
     * @return string Formatted addressId
     */
    private function formatAddressId(string $addressId, array $partner, string $senderIdentity): string
    {
        $formattedAddressId = $addressId;
        $trimLeadingZeroFromDealerCode = $partner['trimLeadingZeroFromDealerCode'] ?? false;
        $dealerPrefix = $partner['dealerPrefix'] ?? '';

        // Trim leading zero if configured to do so
        if ($trimLeadingZeroFromDealerCode
            && strlen($addressId) >= 5
            && str_starts_with($addressId, '0')
        ) {
            $formattedAddressId = substr($addressId, 1, 4);
        }

        // Special formatting for CarMax
        if (strtolower($senderIdentity) === 'carmax') {
            // For CarMax: use only 4 characters if address is greater then 6 characters start from 2nd
            if (strlen($addressId) >= 6) {
                $formattedAddressId = substr($addressId, 1, 4);
            }
        }

        // Remove existing dealer prefix if present
        $formattedAddressId = str_replace($dealerPrefix, '', $formattedAddressId);

        // Apply dealer prefix if configured
        if ($dealerPrefix) {
            $formattedAddressId = $dealerPrefix . $formattedAddressId;
        }

        return $formattedAddressId;
    }

    private function getValidDealerCode(string $dealerCode): ?string
    {
        try {
            $result = $this->lookupDealers->execute(['dealerCode' => $dealerCode]);
            return $result['results'][0]['shipToLocation']['locationId'] ?? null;
        } catch (Exception $e) {
            $this->logger->error('Punchout: Error in validateDealerExists: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate that the addressId is associated with the partner's corpAddressId
     *
     * @param string $dealerCode Formatted dealer code to check
     * @param string $corpAddressId Partner's corpAddressId
     * @return bool True if address is associated with partner
     */
    private function validateAddressAssociatedWithPartner(string $dealerCode, string $corpAddressId): bool
    {
        try {
            // Use lookupCommonDealers to get all valid addresses for this partner
            $params = ['dealerCode' => $corpAddressId];
            $result = $this->lookupCommonDealers->execute($params);
            $commonDealers = $result['results'] ?? [];

            if (empty($commonDealers)) {
                $this->logger->warning("Punchout: No common dealers found for corpAddressId '{$corpAddressId}'");
                return false;
            }

            // Check if the provided dealerCode is in the list of common dealers
            foreach ($commonDealers as $dealer) {
                $validDealerCode = $dealer['dealerCode'] ?? '';
                if ($validDealerCode === $dealerCode) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('Punchout: Error in validateAddressAssociatedWithPartner: ' . $e->getMessage());
            return false;
        }
    }
}
