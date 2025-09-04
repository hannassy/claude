<?php
declare(strict_types=1);
namespace Tirehub\BubbleHint\Controller\Adminhtml\BubbleHint;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\BubbleHint\Api\Data\HintInterface;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;
use Tirehub\BubbleHint\Api\Data\HintInterfaceFactory;

class Save extends Action implements HttpPostActionInterface
{
    private DataObjectHelper $dataObjectHelper;
    private HintInterfaceFactory $hintFactory;
    private HintRepositoryInterface $hintRepository;

    public function __construct(
        Context $context,
        DataObjectHelper $dataObjectHelper,
        HintInterfaceFactory $hintFactory,
        HintRepositoryInterface $hintRepository
    ) {
        parent::__construct($context);
        $this->dataObjectHelper = $dataObjectHelper;
        $this->hintFactory = $hintFactory;
        $this->hintRepository = $hintRepository;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @phpstan-ignore-next-line ("Call to an undefined method")*/
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            $model = $this->hintFactory->create();

            try {
                $this->dataObjectHelper->populateWithArray($model, $data, HintInterface::class);
                $newModel = $this->hintRepository->save($model);
                $id = $newModel->getHintId();
                $this->messageManager->addSuccessMessage(__('You saved the hint.')->render());

            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the hint.')->render());
            }

            return $resultRedirect->setPath('*/*/edit', ['id' => $id ?? 0]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
