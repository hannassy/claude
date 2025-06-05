<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Adminhtml\Session;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_Punchout::session';

    private $resultPageFactory;

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
        $resultPage->setActiveMenu('Tirehub_Punchout::session');
        $resultPage->getConfig()->getTitle()->prepend(__('Punchout Sessions'));

        return $resultPage;
    }
}