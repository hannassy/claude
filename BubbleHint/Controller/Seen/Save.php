<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Controller\Seen;

use Exception;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Tirehub\BubbleHint\Api\HintSeenRepositoryInterface;
use Tirehub\BubbleHint\Model\HintSeen;
use Tirehub\BubbleHint\Model\HintSeenFactory;

class Save implements ActionInterface,HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger,
        private readonly RequestInterface $request,
        private readonly HintSeenRepositoryInterface $hintSeenRepository,
        private readonly CustomerSession $customerSession,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly HintSeenFactory $hintSeenFactory
    ) {
    }

    public function execute(): Json
    {
        $response = $this->jsonFactory->create();

        try {
            $hintId = (int)$this->request->getParam('id');
            $customerId = (int)$this->customerSession->getCustomerId();

            $hintSeen = $this->getHintSeen($hintId, $customerId);
            $seenCount = $hintSeen->getSeenCount() + 1;
            $hintSeen->setSeenCount($seenCount);
            $this->hintSeenRepository->save($hintSeen);

            $result = ['error' => false];
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            $result = ['error' => true, 'message' => $e->getMessage()];
        }

        return $response->setData($result);
    }

    private function getHintSeen(int $hintId, int $customerId): HintSeen
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria->addFilter('hint_id', $hintId);
        $searchCriteria->addFilter('customer_id', $customerId);
        $searchCriteria = $searchCriteria->create();

        $hintSeen = null;

        $list = $this->hintSeenRepository->getList($searchCriteria);
        $items = $list->getItems();
        foreach ($items as $item) {
            $hintSeen = $item;
        }

        if (!$hintSeen) {
            $hintSeen = $this->hintSeenFactory->create();
            $hintSeen->setHintId($hintId);
            $hintSeen->setCustomerId($customerId);
        }

        return $hintSeen;
    }
}
