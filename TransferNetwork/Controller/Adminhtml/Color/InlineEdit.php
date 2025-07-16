<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Color;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\TransferNetwork\Model\ColorFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Color as ColorResource;

class InlineEdit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::color';

    protected $jsonFactory;
    protected $colorFactory;
    protected $colorResource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ColorFactory $colorFactory,
        ColorResource $colorResource
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->colorFactory = $colorFactory;
        $this->colorResource = $colorResource;
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
                foreach (array_keys($postItems) as $colorId) {
                    $color = $this->colorFactory->create();
                    $this->colorResource->load($color, $colorId);
                    try {
                        $color->setData(array_merge($color->getData(), $postItems[$colorId]));
                        $this->colorResource->save($color);
                    } catch (\Exception $e) {
                        $messages[] = $this->getErrorWithColorId(
                            $color,
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

    protected function getErrorWithColorId($color, $errorText): string
    {
        return '[Color ID: ' . $color->getId() . '] ' . $errorText;
    }
}
