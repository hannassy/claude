<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Color;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Tirehub\TransferNetwork\Model\ColorFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color as ColorResource;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::color';

    protected $resultPageFactory;
    protected $coreRegistry;
    protected $colorFactory;
    protected $colorResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        ColorFactory $colorFactory,
        ColorResource $colorResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->colorFactory = $colorFactory;
        $this->colorResource = $colorResource;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');
        $model = $this->colorFactory->create();

        if ($id) {
            $this->colorResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This color no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('transfernetwork_color', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tirehub_TransferNetwork::colors');

        if ($model->getId()) {
            $resultPage->getConfig()->getTitle()->prepend(__('Edit Color: %1', $model->getColorName()));
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Color'));
        }

        return $resultPage;
    }
}
