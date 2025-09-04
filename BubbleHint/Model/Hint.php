<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\BubbleHint\Api\Data\HintInterface;
use Tirehub\BubbleHint\Model\ResourceModel\Hint as HintResource;

class Hint extends AbstractModel implements HintInterface
{
    public function _construct(): void
    {
        $this->_init(HintResource::class);
    }

    public function getHintId(): int
    {
        return (int)$this->getData(self::HINT_ID);
    }

    public function setHintId(int $hintId): void
    {
        $this->setData(self::HINT_ID, $hintId);
    }

    public function getName(): string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): void
    {
        $this->setData(self::NAME, $name);
    }

    public function getText(): string
    {
        return $this->getData(self::TEXT);
    }

    public function setText(string $text): void
    {
        $this->setData(self::TEXT, $text);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): void
    {
        $this->setData(self::IS_ACTIVE, $isActive);
    }
}
