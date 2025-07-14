<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $locationFactory;
    protected $locationResource;
    protected $dataPersistor;

    public function __construct(
        Context $context,
        LocationFactory $locationFactory,
        LocationResource $locationResource,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->locationFactory = $locationFactory;
        $this->locationResource = $locationResource;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('entity_id');
            $model = $this->locationFactory->create();

            if ($id) {
                $this->locationResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            } else {
                unset($data['entity_id']);
            }

            $model->setData($data);

            if (!empty($data['icon'][0]['url'])) {
                $model->setIcon($data['icon'][0]['url']);
            }

            try {
                $this->locationResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the location.'));
                $this->dataPersistor->clear('transfernetwork_location');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the location.'));
            }

            $this->dataPersistor->set('transfernetwork_location', $data);
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $this->getRequest()->getParam('entity_id')]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
