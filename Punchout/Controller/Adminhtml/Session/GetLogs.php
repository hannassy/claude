<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Adminhtml\Session;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\SerializerInterface;
use Tirehub\Punchout\Model\ResourceModel\Log\CollectionFactory as LogCollectionFactory;

class GetLogs extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_Punchout::session';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly LogCollectionFactory $logCollectionFactory,
        private readonly Escaper $escaper,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $sessionId = (int)$this->getRequest()->getParam('session_id');
        $result = $this->resultJsonFactory->create();

        try {
            if (!$sessionId) {
                throw new \Exception('Missing session ID parameter');
            }

            $collection = $this->logCollectionFactory->create();
            $collection->addFieldToFilter('session_id', $sessionId);
            $collection->setOrder('created_at', 'DESC');

            $logs = [];
            foreach ($collection as $log) {
                $context = [];
                if ($log->getData('context')) {
                    try {
                        $context = $this->serializer->unserialize($log->getData('context'));
                    } catch (\Exception $e) {
                        $context = ['raw' => $log->getData('context')];
                    }
                }

                $logs[] = [
                    'level' => $log->getData('level'),
                    'message' => $this->escaper->escapeHtml($log->getData('message')),
                    'context' => $context,
                    'source' => $this->escaper->escapeHtml($log->getData('source')),
                    'created_at' => $log->getData('created_at')
                ];
            }

            $html = $this->generateLogsHtml($sessionId, $logs);

            $result->setData([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    private function generateLogsHtml(int $sessionId, array $logs): string
    {
        $html = '<div class="logs-container">';
        $html .= '<div class="logs-header">';
        $html .= '<div class="logs-title">Session Logs #' . $sessionId . '</div>';
        $html .= '<div class="logs-summary">' . __('Total entries: %1', count($logs)) . '</div>';
        $html .= '</div>';

        if (empty($logs)) {
            $html .= '<div class="message message-notice"><div>' . __('No logs found for this session') . '</div></div>';
        } else {
            $html .= '<div class="logs-filters">';
            $html .= '<select id="log-level-filter" onchange="filterLogs(this.value)">';
            $html .= '<option value="">All Levels</option>';
            $html .= '<option value="debug">Debug</option>';
            $html .= '<option value="info">Info</option>';
            $html .= '<option value="warning">Warning</option>';
            $html .= '<option value="error">Error</option>';
            $html .= '<option value="critical">Critical</option>';
            $html .= '</select>';
            $html .= '</div>';

            $html .= '<div class="logs-entries">';
            foreach ($logs as $log) {
                $levelClass = $this->getLevelClass($log['level']);
                $html .= '<div class="log-entry log-level-' . $log['level'] . '">';
                $html .= '<div class="log-header">';
                $html .= '<span class="log-level ' . $levelClass . '">' . strtoupper($log['level']) . '</span>';
                $html .= '<span class="log-time">' . $log['created_at'] . '</span>';
                $html .= '<span class="log-source">' . $log['source'] . '</span>';
                $html .= '</div>';
                $html .= '<div class="log-message">' . $log['message'] . '</div>';

                if (!empty($log['context'])) {
                    $html .= '<div class="log-context">';
                    $html .= '<a href="#" onclick="toggleContext(this); return false;">Show Context</a>';
                    $html .= '<pre class="context-data" style="display:none;">' . $this->escaper->escapeHtml(json_encode($log['context'], JSON_PRETTY_PRINT)) . '</pre>';
                    $html .= '</div>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';

            $html .= '<script>
                function filterLogs(level) {
                    var entries = document.querySelectorAll(".log-entry");
                    entries.forEach(function(entry) {
                        if (level === "" || entry.classList.contains("log-level-" + level)) {
                            entry.style.display = "block";
                        } else {
                            entry.style.display = "none";
                        }
                    });
                }
                
                function toggleContext(link) {
                    var contextData = link.nextElementSibling;
                    if (contextData.style.display === "none") {
                        contextData.style.display = "block";
                        link.textContent = "Hide Context";
                    } else {
                        contextData.style.display = "none";
                        link.textContent = "Show Context";
                    }
                }
            </script>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getLevelClass(string $level): string
    {
        $classes = [
            'debug' => 'log-level-debug',
            'info' => 'log-level-info',
            'warning' => 'log-level-warning',
            'error' => 'log-level-error',
            'critical' => 'log-level-critical'
        ];

        return $classes[$level] ?? 'log-level-default';
    }
}
