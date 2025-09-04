<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tirehub\BubbleHint\Api\Data\HintSeenInterface;
use Tirehub\BubbleHint\Api\Data\HintSeenInterfaceFactory;
use Tirehub\BubbleHint\Api\Data\HintSeenSearchResultsInterfaceFactory;
use Tirehub\BubbleHint\Api\HintSeenRepositoryInterface;
use Tirehub\BubbleHint\Model\ResourceModel\HintSeen as ResourceHintSeen;
use Tirehub\BubbleHint\Model\ResourceModel\HintSeen\CollectionFactory as HintSeenCollectionFactory;

class HintSeenRepository implements HintSeenRepositoryInterface
{
    private ResourceHintSeen $resource;
    private HintSeenInterfaceFactory $hintSeenFactory;
    private HintSeenCollectionFactory $hintSeenCollectionFactory;
    private HintSeenSearchResultsInterfaceFactory $searchResultsFactory;
    private CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        ResourceHintSeen $resource,
        HintSeenInterfaceFactory $hintSeenFactory,
        HintSeenCollectionFactory $hintSeenCollectionFactory,
        HintSeenSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->hintSeenFactory = $hintSeenFactory;
        $this->hintSeenCollectionFactory = $hintSeenCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function save(HintSeenInterface $hintSeen): HintSeenInterface
    {
        try {
            /** @phpstan-ignore-next-line  */
            $this->resource->save($hintSeen);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the hintSeen: %1',
                $exception->getMessage()
            ));
        }
        return $hintSeen;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function get(int $hintSeenId): HintSeenInterface
    {
        $hintSeen = $this->hintSeenFactory->create();
        /** @phpstan-ignore-next-line  */
        $this->resource->load($hintSeen, $hintSeenId);
        if (!$hintSeen->getHintSeenId()) {
            throw new NoSuchEntityException(__('HintSeen with id "%1" does not exist.', $hintSeenId));
        }
        return $hintSeen;
    }
    /** @phpstan-ignore-next-line */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->hintSeenCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }

        /** @phpstan-ignore-next-line */
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(HintSeenInterface $hintSeen): bool
    {
        try {
            $hintSeenModel = $this->hintSeenFactory->create();
            /** @phpstan-ignore-next-line  */
            $this->resource->load($hintSeenModel, $hintSeen->getHintSeenId());
            /** @phpstan-ignore-next-line  */
            $this->resource->delete($hintSeenModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the HintSeen: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $hintSeenId): bool
    {
        return $this->delete($this->get($hintSeenId));
    }
}
