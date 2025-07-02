<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\TransferNetwork\Model\LocationRelationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;

class InlineEdit extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $jsonFactory;
    protected $relationFactory;
    protected $relationResource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LocationRelationFactory $relationFactory,
        LocationRelationResource $relationResource
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
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
                foreach (array_keys($postItems) as $relationId) {
                    $relation = $this->relationFactory->create();
                    $this->relationResource->load($relation, $relationId);
                    try {
                        $relation->setData(array_merge($relation->getData(), $postItems[$relationId]));
                        $this->relationResource->save($relation);
                    } catch (\Exception $e) {
                        $messages[] = $this->getErrorWithRelationId(
                            $relation,
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

    protected function getErrorWithRelationId($relation, $errorText): string
    {
        return '[Relation ID: ' . $relation->getId() . '] ' . $errorText;
    }
}