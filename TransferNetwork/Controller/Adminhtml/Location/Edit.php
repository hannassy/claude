<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Tirehub\TransferNetwork\Model\LocationFactory;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $resultPageFactory;
    protected $coreRegistry;
    protected $locationFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        LocationFactory $locationFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->locationFactory = $locationFactory;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('location_id');
        $model = $this->locationFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('transfernetwork_location', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TransferNetwork::locations');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Location'));

        return $resultPage;
    }
}