<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api\Data;

interface HintSeenInterface
{
    public const HINT_SEEN_ID = 'hint_seen_id';
    public const HINT_ID      = 'hint_id';
    public const CUSTOMER_ID  = 'customer_id';
    public const SEEN_COUNT   = 'seen_count';

    public function getHintSeenId(): int;

    public function setHintSeenId(int $hintSeenId): void;

    public function getHintId(): int;

    public function setHintId(int $hintId): void;

    public function getCustomerId(): int;

    public function setCustomerId(int $customerId): void;

    public function getSeenCount(): int;

    public function setSeenCount(int $seenCount): void;
}
