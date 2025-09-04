<?php
declare(strict_types=1);

namespace Tirehub\BubbleHint\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tirehub\BubbleHint\Api\Data\HintInterface;
use Tirehub\BubbleHint\Api\Data\HintInterfaceFactory;
use Tirehub\BubbleHint\Api\Data\HintSearchResultsInterfaceFactory;
use Tirehub\BubbleHint\Api\HintRepositoryInterface;
use Tirehub\BubbleHint\Model\ResourceModel\Hint as HintResource;
use Tirehub\BubbleHint\Model\ResourceModel\Hint\CollectionFactory as HintCollectionFactory;

class HintRepository implements HintRepositoryInterface
{
    private HintResource $resource;
    private HintInterfaceFactory $hintFactory;
    private HintCollectionFactory $hintCollectionFactory;
    private HintSearchResultsInterfaceFactory $searchResultsFactory;
    private CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        HintResource $resource,
        HintInterfaceFactory $hintFactory,
        HintCollectionFactory $hintCollectionFactory,
        HintSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->hintFactory = $hintFactory;
        $this->hintCollectionFactory = $hintCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function save(HintInterface $hint): HintInterface
    {
        try {
            /** @phpstan-ignore-next-line  */
            $this->resource->save($hint);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the hint: %1',
                $exception->getMessage()
            ));
        }
        return $hint;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function get(int $hintId): HintInterface
    {
        $hint = $this->hintFactory->create();
        /** @phpstan-ignore-next-line  */
        $this->resource->load($hint, $hintId);
        if (!$hint->getHintId()) {
            throw new NoSuchEntityException(__('Hint with id "%1" does not exist.', $hintId));
        }
        return $hint;
    }
    /** @phpstan-ignore-next-line */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->hintCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

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
    public function delete(HintInterface $hint): bool
    {
        try {
            $hintModel = $this->hintFactory->create();
            /** @phpstan-ignore-next-line  */
            $this->resource->load($hintModel, $hint->getHintId());
            /** @phpstan-ignore-next-line  */
            $this->resource->delete($hintModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the Hint: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $hintId): bool
    {
        return $this->delete($this->get($hintId));
    }
}
