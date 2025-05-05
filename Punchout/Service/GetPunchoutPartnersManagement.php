<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Utility\Api\CacheManagementInterface;
use Tirehub\ApiMiddleware\Api\Request\Punchout\GetPunchoutPartnersInterface;

class GetPunchoutPartnersManagement
{
    public function __construct(
        private readonly GetPunchoutPartnersInterface $getPunchoutPartners,
        private readonly CacheManagementInterface $cacheManagement,
        private readonly string $cacheId = '',
        private readonly int $cacheLifetime = 0
    ) {
    }

    public function getResult(): array
    {
        $data = $this->cacheManagement->get($this->cacheId);
        if (!$data) {
            $data = $this->getPunchoutPartners->execute();
            $this->cacheManagement->save($this->cacheId, $this->cacheLifetime, $data);
        }

        return $data;
    }
}
