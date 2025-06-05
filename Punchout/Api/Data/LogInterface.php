<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Api\Data;

interface LogInterface
{
    const ENTITY_ID = 'entity_id';
    const SESSION_ID = 'session_id';
    const LEVEL = 'level';
    const MESSAGE = 'message';
    const CONTEXT = 'context';
    const SOURCE = 'source';
    const CREATED_AT = 'created_at';

    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    public function log(string $level, string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logDebug(string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logInfo(string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logWarning(string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logError(string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logCritical(string $message, array $context = [], ?string $buyerCookie = null): void;

    public function logForSession(int $sessionId, string $level, string $message, array $context = []): void;
}