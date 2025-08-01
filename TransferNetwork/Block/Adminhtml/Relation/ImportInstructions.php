<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Block\Adminhtml\Relation;

use Magento\Backend\Block\Template;

class ImportInstructions extends Template
{
    protected $_template = 'Tirehub_TransferNetwork::relation/import_instructions.phtml';

    public function getExportUrl(): string
    {
        return $this->getUrl('transfernetwork/relation/export');
    }
}
