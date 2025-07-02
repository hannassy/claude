<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $filter;
    protected $collectionFactory;
    protected $locationResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        LocationResource $locationResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->locationResource = $locationResource;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $location) {
            $this->locationResource->delete($location);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
}
