<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $locationFactory;
    protected $locationResource;

    public function __construct(
        Context $context,
        LocationFactory $locationFactory,
        LocationResource $locationResource
    ) {
        parent::__construct($context);
        $this->locationFactory = $locationFactory;
        $this->locationResource = $locationResource;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $model = $this->locationFactory->create();
                $this->locationResource->load($model, $id);
                $this->locationResource->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the location.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a location to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
