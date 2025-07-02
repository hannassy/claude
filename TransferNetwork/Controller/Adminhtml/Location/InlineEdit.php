<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class InlineEdit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $jsonFactory;
    protected $locationFactory;
    protected $locationResource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LocationFactory $locationFactory,
        LocationResource $locationResource
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->locationFactory = $locationFactory;
        $this->locationResource = $locationResource;
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $locationId) {
                    $location = $this->locationFactory->create();
                    $this->locationResource->load($location, $locationId);
                    try {
                        $location->setData(array_merge($location->getData(), $postItems[$locationId]));
                        $this->locationResource->save($location);
                    } catch (\Exception $e) {
                        $messages[] = $this->getErrorWithLocationId(
                            $location,
                            __($e->getMessage())
                        );
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    protected function getErrorWithLocationId($location, $errorText): string
    {
        return '[Location ID: ' . $location->getId() . '] ' . $errorText;
    }
}