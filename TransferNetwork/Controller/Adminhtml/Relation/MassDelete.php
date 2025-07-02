<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $filter;
    protected $collectionFactory;
    protected $relationResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        LocationRelationResource $relationResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->relationResource = $relationResource;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $relation) {
            $this->relationResource->delete($relation);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
}