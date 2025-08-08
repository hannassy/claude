<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class CxmlResponse extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (!empty($item['cxml_response'])) {
                    $item[$this->getData('name')] = [
                        'view' => [
                            'callback' => [
                                [
                                    'provider' => 'punchout_session_listing.punchout_session_listing.cxml_response_modal',
                                    'target' => 'openModal'
                                ],
                                [
                                    'provider' => 'punchout_session_listing.punchout_session_listing.cxml_response_modal.cxml_response_content',
                                    'target' => 'updateData',
                                    'params' => [
                                        'id' => $item['entity_id']
                                    ]
                                ]
                            ],
                            'label' => __('View Response'),
                            '__disableTmpl' => true
                        ]
                    ];
                } else {
                    $item[$this->getData('name')] = '';
                }
            }
        }

        return $dataSource;
    }
}
