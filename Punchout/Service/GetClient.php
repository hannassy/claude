<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Tirehub\Punchout\Api\GetClientInterface;
use Tirehub\Punchout\Model\Client\DefaultClient;
use Tirehub\Punchout\Model\Client\ClientInterface;
use Magento\Framework\App\RequestInterface;

class GetClient implements GetClientInterface
{
    public function __construct(
        private readonly DefaultClient $defaultClient
    ) {
    }

    public function execute(): ClientInterface
    {
        return $this->defaultClient;
    }
}
