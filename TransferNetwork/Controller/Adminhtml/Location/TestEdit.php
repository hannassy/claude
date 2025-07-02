<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Tirehub\TransferNetwork\Model\LocationFactory;

class TestEdit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $resultPageFactory;
    protected $coreRegistry;
    protected $locationFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        LocationFactory $locationFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->locationFactory = $locationFactory;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');
        $model = $this->locationFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        // Simple HTML response for testing
        $html = '
        <div style="padding: 20px;">
            <h1>Edit Location (Simple Test)</h1>
            <form action="' . $this->getUrl('transfernetwork/location/save') . '" method="post">
                <input type="hidden" name="form_key" value="' . $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class)->getFormKey() . '">
                <input type="hidden" name="entity_id" value="' . $model->getId() . '">
                
                <p><label>Location ID: <input type="text" name="location_id" value="' . $model->getLocationId() . '"></label></p>
                <p><label>Location Name: <input type="text" name="location_name" value="' . $model->getLocationName() . '"></label></p>
                <p><label>Latitude: <input type="text" name="latitude" value="' . $model->getLatitude() . '"></label></p>
                <p><label>Longitude: <input type="text" name="longitude" value="' . $model->getLongitude() . '"></label></p>
                
                <p>
                    <button type="submit">Save</button>
                    <a href="' . $this->getUrl('transfernetwork/location') . '">Back</a>
                </p>
            </form>
        </div>';

        $this->getResponse()->setBody($html);
        return;
    }
}