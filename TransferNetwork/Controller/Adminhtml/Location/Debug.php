<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class Debug extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $locationFactory;
    protected $locationResource;

    public function __construct(
        Context $context,
        LocationFactory $locationFactory,
        LocationResource $locationResource
    ) {
        parent::__construct($context);
        $this->locationFactory = $locationFactory;
        $this->locationResource = $locationResource;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id', 1);

        try {
            $location = $this->locationFactory->create();
            $this->locationResource->load($location, $id);

            $html = '<h1>Debug Location Data</h1>';
            $html .= '<p><strong>Entity ID:</strong> ' . $location->getId() . '</p>';
            $html .= '<p><strong>Location ID:</strong> ' . $location->getLocationId() . '</p>';
            $html .= '<p><strong>Location Name:</strong> ' . $location->getLocationName() . '</p>';
            $html .= '<p><strong>Latitude:</strong> ' . $location->getLatitude() . '</p>';
            $html .= '<p><strong>Longitude:</strong> ' . $location->getLongitude() . '</p>';
            $html .= '<p><strong>All Data:</strong></p>';
            $html .= '<pre>' . print_r($location->getData(), true) . '</pre>';

            $this->getResponse()->setBody($html);

        } catch (\Exception $e) {
            $this->getResponse()->setBody('Error: ' . $e->getMessage());
        }
    }
}