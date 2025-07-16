<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Color;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Tirehub\TransferNetwork\Model\ResourceModel\Color\CollectionFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color as ColorResource;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::color';

    protected $filter;
    protected $collectionFactory;
    protected $colorResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ColorResource $colorResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->colorResource = $colorResource;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $color) {
            $this->colorResource->delete($color);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
}
