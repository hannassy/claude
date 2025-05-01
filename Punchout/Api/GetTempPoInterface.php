<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface GetTempPoInterface
{
    public const TEMPPPO_PREFIX = 'TEMPPO';
    public function execute(): string;
}
