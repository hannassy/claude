<?php
declare(strict_types=1);
// phpcs:ignoreFile
namespace Tirehub\BubbleHint\Controller\Adminhtml\BubbleHint;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use RuntimeException;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;

class InlineEdit extends Action implements HttpPostActionInterface
{
    private JsonFactory $jsonFactory;
    private HintRepositoryInterface $hintRepository;

    public function __construct(
        Context $context,
        HintRepositoryInterface $hintRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->hintRepository = $hintRepository;
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        $postItems = $this->getRequest()->getParam('items', []);
        if (!($this->getRequest()->getParam('isAjax') && count($postItems))) {
            return $resultJson->setData(
                [
                    'messages' => [__('Please correct the data sent.')->render()],
                    'error' => true,
                ]
            );
        }

        foreach (array_keys($postItems) as $item) {

            $hint = $this->hintRepository->get((int)$item);
            try {
                /** @phpstan-ignore-next-line ("Call to an undefined method")*/
                $mergedData = array_merge($hint->getData(), array_values($postItems)[0]);

                foreach ($mergedData as $key => $value) {
                    /** @phpstan-ignore-next-line ("Call to an undefined method")*/
                    $hint->setData($key, $value);
                }
                $this->hintRepository->save($hint);

            } catch (RuntimeException $e) {
                $messages[] = $e->getMessage();
                $error = true;
            } catch (Exception $e) {
                $messages[] = __('Something went wrong while saving the hint.');
                $error = true;
            }
        }

        return $resultJson->setData(
            [
                'messages' => $messages,
                'error' => $error
            ]
        );
    }
}
