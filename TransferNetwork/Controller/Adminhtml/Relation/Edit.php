<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Tirehub\TransferNetwork\Model\LocationRelationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $resultPageFactory;
    protected $coreRegistry;
    protected $relationFactory;
    protected $relationResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        LocationRelationFactory $relationFactory,
        LocationRelationResource $relationResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('relation_id');
        $model = $this->relationFactory->create();

        if ($id) {
            $this->relationResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This relation no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('transfernetwork_relation', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TransferNetwork::relations');

        if ($model->getId()) {
            $resultPage->getConfig()->getTitle()->prepend(__('Edit Relation: #%1', $model->getRelationId()));
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Relation'));
        }

        return $resultPage;
    }
}