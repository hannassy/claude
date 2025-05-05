<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface IsPunchoutModeInterface
{
    public function execute(): bool;
}
