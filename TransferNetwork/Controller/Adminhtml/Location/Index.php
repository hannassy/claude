<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TransferNetwork::locations');
        $resultPage->getConfig()->getTitle()->prepend(__('Transfer Network Locations'));

        return $resultPage;
    }
}