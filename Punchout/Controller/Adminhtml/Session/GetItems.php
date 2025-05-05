<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Adminhtml\Session;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\Punchout\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Magento\Framework\Escaper;

class GetItems extends Action
{
    public const ADMIN_RESOURCE = 'Tirehub_Punchout::session';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $buyerCookie = $this->getRequest()->getParam('buyer_cookie');
        $result = $this->resultJsonFactory->create();

        try {
            if (empty($buyerCookie)) {
                throw new \Exception('Missing buyer cookie parameter');
            }

            $collection = $this->itemCollectionFactory->create();
            $collection->addFieldToFilter('token', $buyerCookie);

            $items = [];

            foreach ($collection as $item) {
                $itemData = $item->getData();

                $items[] = [
                    'sku' => $this->escaper->escapeHtml($itemData['item_id']),
                    'qty' => (int)$itemData['quantity'],
                    'status' => $this->escaper->escapeHtml($itemData['status']),
                    'created_at' => $itemData['created_at']
                ];
            }

            // Create HTML response with session details and items
            $html = $this->generateItemsHtml($buyerCookie, $items);

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

    private function generateItemsHtml(string $buyerCookie, array $items): string
    {
        $html = '<div class="items-container">';
        $html .= '<div class="items-header">';
        $html .= '<div class="items-title">Requested Items for Buyer Cookie: ' . $this->escaper->escapeHtml($buyerCookie) . '</div>';
        $html .= '</div>';

        if (empty($items)) {
            $html .= '<div class="message message-notice"><div>' . __('No items found for this buyer cookie') . '</div></div>';
        } else {
            $html .= '<div class="items-grid">';
            $html .= '<table class="admin__table-secondary">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . __('SKU') . '</th>';
            $html .= '<th>' . __('Quantity') . '</th>';
            $html .= '<th>' . __('Status') . '</th>';
            $html .= '<th>' . __('Created At') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ($items as $item) {
                $html .= '<tr>';
                $html .= '<td>' . $item['sku'] . '</td>';
                $html .= '<td>' . $item['qty'] . '</td>';
                $html .= '<td>' . $this->formatStatus($item['status']) . '</td>';
                $html .= '<td>' . $item['created_at'] . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function formatStatus(string $status): string
    {
        $statusClasses = [
            'pending' => 'grid-severity-notice',
            'added' => 'grid-severity-notice',
            'processed' => 'grid-severity-notice',
            'error' => 'grid-severity-critical',
            'completed' => 'grid-severity-notice'
        ];

        $class = $statusClasses[$status] ?? 'grid-severity-minor';

        return '<span class="' . $class . '"><span>' . ucfirst($status) . '</span></span>';
    }
}
