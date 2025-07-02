<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tirehub\TransferNetwork\Model\LocationRelationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $relationFactory;
    protected $relationResource;

    public function __construct(
        Context $context,
        LocationRelationFactory $relationFactory,
        LocationRelationResource $relationResource
    ) {
        parent::__construct($context);
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('relation_id');

        if ($id) {
            try {
                $model = $this->relationFactory->create();
                $this->relationResource->load($model, $id);
                $this->relationResource->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the relation.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['relation_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a relation to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}