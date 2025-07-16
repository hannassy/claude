<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Color;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tirehub\TransferNetwork\Model\ColorFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color as ColorResource;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::color';

    protected $colorFactory;
    protected $colorResource;

    public function __construct(
        Context $context,
        ColorFactory $colorFactory,
        ColorResource $colorResource
    ) {
        parent::__construct($context);
        $this->colorFactory = $colorFactory;
        $this->colorResource = $colorResource;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $model = $this->colorFactory->create();
                $this->colorResource->load($model, $id);
                $this->colorResource->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the color.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a color to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
