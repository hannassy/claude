<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Tirehub\TransferNetwork\Model\LocationRelationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $relationFactory;
    protected $relationResource;
    protected $dataPersistor;

    public function __construct(
        Context $context,
        LocationRelationFactory $relationFactory,
        LocationRelationResource $relationResource,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('relation_id');
            $model = $this->relationFactory->create();

            if ($id) {
                $this->relationResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This relation no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            } else {
                unset($data['relation_id']);
            }

            $model->setData($data);

            try {
                $this->relationResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the relation.'));
                $this->dataPersistor->clear('transfernetwork_relation');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['relation_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the relation.'));
            }

            $this->dataPersistor->set('transfernetwork_relation', $data);
            return $resultRedirect->setPath('*/*/edit', ['relation_id' => $this->getRequest()->getParam('relation_id')]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}