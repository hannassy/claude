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
        if (!$domain || !$identity || !$sharedSecret) {
            throw new LocalizedException(__('Is missing required attributes'));
        }

        $result = $this->getPunchoutPartnersManagement->getResult();
        $partner = [];

        foreach ($result as $item) {
            $itemDomain = strtolower($item['domain'] ?? '');
            if ($itemDomain === strtolower($domain)) {
                $partner = $item;
                break;
            }
        }

        if (!$partner) {
            throw new LocalizedException(__('Unable to find identity match!'));
        }

        if ($partner['identity'] !== $identity) {
            throw new LocalizedException(__('Unable to find identity match!'));
        }

        if ($partner['sharedSecret'] !== $sharedSecret) {
            throw new LocalizedException(__('Invalid shared secret!'));
        }
    }
}
