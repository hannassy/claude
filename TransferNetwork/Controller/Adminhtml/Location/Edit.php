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
        // Debug logging
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/location_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $id = $this->getRequest()->getParam('entity_id');
        $logger->info('Edit controller called with entity_id: ' . $id);

        $model = $this->locationFactory->create();

        if ($id) {
            $model->load($id);
            $logger->info('Loaded location with ID: ' . $model->getId() . ', Location ID: ' . $model->getLocationId());

            if (!$model->getId()) {
                $logger->err('Location not found for entity_id: ' . $id);
                $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('transfernetwork_location', $model);
        $logger->info('Location registered in registry');

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TransferNetwork::locations');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Location'));

        $logger->info('Edit page created successfully');
        return $resultPage;
    }
}