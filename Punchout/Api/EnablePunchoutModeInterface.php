<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface EnablePunchoutModeInterface
{
    public function execute(string $buyerCookie): void;
}
