<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class CxmlRequest extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
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
                if (isset($item['cxml_request']) && !empty($item['cxml_request'])) {
                    // Use modal opener configuration
                    $item[$this->getData('name')] = [
                        'view' => [
                            'callback' => [
                                [
                                    'provider' => 'punchout_session_listing.punchout_session_listing.cxml_request_modal',
                                    'target' => 'openModal'
                                ],
                                [
                                    'provider' => 'punchout_session_listing.punchout_session_listing.cxml_request_modal.cxml_content',
                                    'target' => 'updateData',
                                    'params' => [
                                        'id' => $item['entity_id']
                                    ]
                                ]
                            ],
                            'label' => __('View'),
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
