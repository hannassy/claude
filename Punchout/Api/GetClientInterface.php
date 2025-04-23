<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

use Tirehub\Punchout\Model\Client\ClientInterface;

interface GetClientInterface
{
    public function execute(): ClientInterface;
}
