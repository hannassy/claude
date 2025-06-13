<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TirePromo::promo_code_log';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TirePromo::promo_code_log');
        $resultPage->getConfig()->getTitle()->prepend(__('Promo Code Logs'));

        return $resultPage;
    }
}
