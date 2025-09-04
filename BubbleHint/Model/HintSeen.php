<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model;

use Magento\Framework\Model\AbstractModel;
use Tirehub\BubbleHint\Api\Data\HintSeenInterface;
use Tirehub\BubbleHint\Model\ResourceModel\HintSeen as HintSeenResource;

class HintSeen extends AbstractModel implements HintSeenInterface
{
    protected $_idFieldName = self::HINT_SEEN_ID;

    public function _construct(): void
    {
        $this->_init(HintSeenResource::class);
    }

    public function getHintSeenId(): int
    {
        return (int)$this->getData(self::HINT_SEEN_ID);
    }

    public function setHintSeenId(int $hintSeenId): void
    {
        $this->setData(self::HINT_SEEN_ID, $hintSeenId);
    }

    public function getHintId(): int
    {
        return (int)$this->getData(self::HINT_ID);
    }

    public function setHintId(int $hintId): void
    {
        $this->setData(self::HINT_ID, $hintId);
    }

    public function getCustomerId(): int
    {
        return (int)$this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): void
    {
        $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getSeenCount(): int
    {
        return (int)$this->getData(self::SEEN_COUNT);
    }

    public function setSeenCount(int $seenCount): void
    {
        $this->setData(self::SEEN_COUNT, $seenCount);
    }
}
