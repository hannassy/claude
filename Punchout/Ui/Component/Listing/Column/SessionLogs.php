<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tirehub\Punchout\Model\ResourceModel\Log\CollectionFactory as LogCollectionFactory;

class SessionLogs extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly LogCollectionFactory $logCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['entity_id'])) {
                    $sessionId = $item['entity_id'];

                    $collection = $this->logCollectionFactory->create();
                    $collection->addFieldToFilter('session_id', $sessionId);
                    $logCount = $collection->getSize();

                    if ($logCount > 0) {
                        $errorCollection = $this->logCollectionFactory->create();
                        $errorCollection->addFieldToFilter('session_id', $sessionId);
                        $errorCollection->addFieldToFilter('level', ['in' => ['error', 'critical']]);
                        $errorCount = $errorCollection->getSize();

                        $label = __('View Logs (%1)', $logCount);
                        if ($errorCount > 0) {
                            $label = __('View Logs (%1, %2 errors)', $logCount, $errorCount);
                        }

                        $item[$this->getData('name')] = [
                            'view' => [
                                'callback' => [
                                    [
                                        'provider' => 'punchout_session_listing.punchout_session_listing.session_logs_modal',
                                        'target' => 'openModal'
                                    ],
                                    [
                                        'provider' => 'punchout_session_listing.punchout_session_listing.session_logs_modal.logs_content',
                                        'target' => 'updateData',
                                        'params' => [
                                            'session_id' => $sessionId
                                        ]
                                    ]
                                ],
                                'label' => $label,
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
