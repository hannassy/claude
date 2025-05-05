<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;

class Credentials
{
    public function __construct(
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute($domain, $identity, $sharedSecret): void
    {
        $result = $this->getPunchoutPartnersManagement->getResult();
        $partner = [];

        foreach ($result as $item) {
            $itemDomain = strtolower($item['domain'] ?? '');
            if ($itemDomain === strtolower($domain)) {
                $partner = $item;
                break;
            }
        }

        // Check if partner exists
        if (!$partner) {
            throw new LocalizedException(__('invalid_identity'));
        }

        // Check identity
        if ($partner['identity'] !== $identity) {
            throw new LocalizedException(__('invalid_identity'));
        }

        // Check shared secret
        if ($partner['sharedSecret'] !== $sharedSecret) {
            throw new LocalizedException(__('invalid_shared_secret'));
        }
    }
}
