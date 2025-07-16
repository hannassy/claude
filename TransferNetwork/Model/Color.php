<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Model;

use Magento\Framework\Model\AbstractModel;

class Color extends AbstractModel
{
    const CACHE_TAG = 'tirehub_transfernetwork_color';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'tirehub_transfernetwork_color';

    protected function _construct()
    {
        $this->_init(\Tirehub\TransferNetwork\Model\ResourceModel\Color::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getColorName(): ?string
    {
        return $this->getData('color_name');
    }

    public function setColorName(string $colorName): self
    {
        return $this->setData('color_name', $colorName);
    }

    public function getColorCode(): ?string
    {
        return $this->getData('color_code');
    }

    public function setColorCode(string $colorCode): self
    {
        return $this->setData('color_code', $colorCode);
    }
}
