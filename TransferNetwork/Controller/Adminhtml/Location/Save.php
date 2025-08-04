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

            $data = $this->processIconData($data, $model);
            $model->setData($data);

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

    private function processIconData(array $data, $model): array
    {
        if (!array_key_exists('icon', $data)) {
            if ($model->getId() && $model->getIcon()) {
                $data['icon'] = null;
            }
            return $data;
        }

        $iconData = $data['icon'];

        if (is_array($iconData)) {
            if (empty($iconData)) {
                $data['icon'] = null;
            } elseif (isset($iconData['delete']) && $iconData['delete'] == '1') {
                $data['icon'] = null;
            } elseif (!empty($iconData[0]['url'])) {
                $iconUrl = $iconData[0]['url'];
                $data['icon'] = $this->normalizeIconPath($iconUrl);
            } elseif (!empty($iconData[0]['name']) && !empty($iconData[0]['tmp_name'])) {
                $data['icon'] = $iconData[0]['url'] ?? '';
            } else {
                $data['icon'] = null;
            }
        } elseif (is_string($iconData) && trim($iconData) === '') {
            $data['icon'] = null;
        }

        return $data;
    }

    private function normalizeIconPath(string $iconPath): string
    {
        if (str_starts_with($iconPath, 'http://') || str_starts_with($iconPath, 'https://')) {
            $parsedUrl = parse_url($iconPath);
            return $parsedUrl['path'] ?? $iconPath;
        }

        if (str_starts_with($iconPath, '/media/')) {
            return ltrim($iconPath, '/media/');
        }

        return $iconPath;
    }
}
