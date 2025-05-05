<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api;

interface RequestStatusInterface
{
    public const INITIALIZED = 'initialized';
    public const INITIATED = 'initiated';
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const FAILED = 'failed';
    public const FINISHED = 'finished';
}
