<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface GetTempPoInterface
{
    public function execute(): string;
}
