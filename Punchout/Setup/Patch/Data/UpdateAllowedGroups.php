<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class UpdateAllowedGroups implements DataPatchInterface
{
    private const XML_PATH = 'silk_b2b/registration/allowed_groups';

    private const ACCESS_URLS = [
        'punchout_portal_index',
        'punchout_portal_submit',
        'punchout_index_index',
        'punchout_setup_item',
        'punchout_setup_request',
        'punchout_shopping_start',
    ];

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(): self
    {
        $urls = $this->scopeConfig->getValue(
            'silk_b2b/registration/allowed_groups',
            ScopeInterface::SCOPE_STORE
        );

        $urls = explode(',', $urls);
        $urls = array_merge($urls, self::ACCESS_URLS);
        $urls = array_unique($urls);
        $urls = implode(',', $urls);

        $this->configWriter->save(self::XML_PATH, $urls);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
