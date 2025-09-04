<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Api\Data;

interface HintInterface
{
    public const HINT_ID   = 'hint_id';
    public const TEXT      = 'text';
    public const NAME      = 'name';
    public const IS_ACTIVE = 'is_active';

    public function getHintId(): int;

    public function setHintId(int $hintId): void;

    public function getName(): string;

    public function setName(string $name): void;

    public function getText(): string;

    public function setText(string $text): void;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): void;
}
