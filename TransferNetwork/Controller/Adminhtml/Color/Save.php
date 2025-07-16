<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Color;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Tirehub\TransferNetwork\Model\ColorFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color as ColorResource;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::color';

    protected $colorFactory;
    protected $colorResource;
    protected $dataPersistor;

    public function __construct(
        Context $context,
        ColorFactory $colorFactory,
        ColorResource $colorResource,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->colorFactory = $colorFactory;
        $this->colorResource = $colorResource;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('entity_id');
            $model = $this->colorFactory->create();

            if ($id) {
                $this->colorResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This color no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            } else {
                unset($data['entity_id']);
            }

            $model->setData($data);

            try {
                $this->colorResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the color.'));
                $this->dataPersistor->clear('transfernetwork_color');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the color.'));
            }

            $this->dataPersistor->set('transfernetwork_color', $data);
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $this->getRequest()->getParam('entity_id')]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
