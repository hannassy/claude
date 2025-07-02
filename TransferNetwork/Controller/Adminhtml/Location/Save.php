<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class Save extends Action
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
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('location_id');
            $model = $this->locationFactory->create();

            if ($id) {
                $this->locationResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $this->locationResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the location.'));

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['location_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            return $resultRedirect->setPath('*/*/edit', ['location_id' => $this->getRequest()->getParam('location_id')]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}