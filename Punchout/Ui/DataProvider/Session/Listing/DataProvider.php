<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\DataProvider\Session\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use Tirehub\Punchout\Model\ResourceModel\Session\CollectionFactory;

class DataProvider extends UiDataProvider
{
    private $collectionFactory;
    private $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );

        $this->collectionFactory = $collectionFactory;
    }

    protected function searchResultToOutput($searchResult)
    {
        $arrItems = [];
        $arrItems['items'] = [];

        foreach ($searchResult->getItems() as $item) {
            $itemData = $item->getData();

            // Format dates
            if (isset($itemData['created_at'])) {
                $itemData['created_at'] = date('Y-m-d H:i:s', strtotime($itemData['created_at']));
            }

            if (isset($itemData['updated_at'])) {
                $itemData['updated_at'] = date('Y-m-d H:i:s', strtotime($itemData['updated_at']));
            }

            // Set has_cxml flag for UI rendering
            if (isset($itemData['cxml_request']) && !empty($itemData['cxml_request'])) {
                $itemData['has_cxml'] = true;
            } else {
                $itemData['has_cxml'] = false;

                // Don't include potentially large XML content in grid data
                if (isset($itemData['cxml_request'])) {
                    // Just indicate it exists but don't send the whole content
                    $itemData['cxml_request'] = !empty($itemData['cxml_request']);
                }
            }

            $arrItems['items'][] = $itemData;
        }

        $arrItems['totalRecords'] = $searchResult->getTotalCount();

        return $arrItems;
    }

    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === 'entity_id') {
            $filter->setField('main_table.' . $filter->getField());
        }

        parent::addFilter($filter);
    }

    public function getData()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();
        }

        return parent::getData();
    }
}
