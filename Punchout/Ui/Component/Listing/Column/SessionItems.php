<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tirehub\Punchout\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class SessionItems extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['buyer_cookie'])) {
                    $buyerCookie = $item['buyer_cookie'];

                    // Check if this session has related items
                    $collection = $this->itemCollectionFactory->create();
                    $collection->addFieldToFilter('token', $buyerCookie);
                    $itemCount = $collection->getSize();

                    if ($itemCount > 0) {
                        // Use modal opener configuration
                        $item[$this->getData('name')] = [
                            'view' => [
                                'callback' => [
                                    [
                                        'provider' => 'punchout_session_listing.punchout_session_listing.session_items_modal',
                                        'target' => 'openModal'
                                    ],
                                    [
                                        'provider' => 'punchout_session_listing.punchout_session_listing.session_items_modal.items_content',
                                        'target' => 'updateData',
                                        'params' => [
                                            'buyer_cookie' => $buyerCookie
                                        ]
                                    ]
                                ],
                                'label' => __('View Items (%1)', $itemCount),
                                '__disableTmpl' => true
                            ]
                        ];
                    } else {
                        $item[$this->getData('name')] = '';
                    }
                } else {
                    $item[$this->getData('name')] = '';
                }
            }
        }

        return $dataSource;
    }
}
