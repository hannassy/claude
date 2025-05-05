<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Adminhtml\Session;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\Punchout\Model\SessionFactory;
use Magento\Framework\Escaper;

class GetCxml extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_Punchout::session';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var SessionFactory
     */
    private $sessionFactory;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param SessionFactory $sessionFactory
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SessionFactory $sessionFactory,
        Escaper $escaper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->sessionFactory = $sessionFactory;
        $this->escaper = $escaper;
    }

    /**
     * Get cXML content for session
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $result = $this->resultJsonFactory->create();

        try {
            $session = $this->sessionFactory->create()->load($id);

            if (!$session->getId()) {
                throw new \Exception(__('Session not found'));
            }

            $cxmlRequest = $session->getData('cxml_request');

            if (empty($cxmlRequest)) {
                throw new \Exception(__('No cXML request data available for this session'));
            }

            // Format XML with indentation
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($cxmlRequest);
            $formattedXml = $dom->saveXML();

            // Escape XML content
            $escapedXml = $this->escaper->escapeHtml($formattedXml);

            // Create HTML response with session details and formatted XML
            $html = '<div class="xml-container">';
            $html .= '<div class="xml-header">';
            $html .= '<div class="xml-title">cXML Request for Session #' . $this->escaper->escapeHtml($id) . '</div>';
            $html .= '<div class="xml-details">';
            $html .= '<div><strong>Partner:</strong> ' . $this->escaper->escapeHtml($session->getData('partner_identity')) . '</div>';
            $html .= '<div><strong>Date:</strong> ' . $this->escaper->escapeHtml($session->getData('created_at')) . '</div>';
            $html .= '</div></div>';

            // Add simple buttons to copy/download
            $html .= '<div class="xml-actions">';
            $html .= '<button type="button" class="action-secondary" id="copy-xml-btn" onclick="copyXmlToClipboard(\'xml-content\')">Copy to Clipboard</button>';
            $html .= '<button type="button" class="action-secondary" id="download-xml-btn" onclick="downloadXml(\'' . $this->escaper->escapeHtml(addslashes($formattedXml)) . '\', \'cxml-request-' . $id . '.xml\')">Download XML</button>';
            $html .= '</div>';

            // Add raw XML content in pre tag for easy copy/paste
            $html .= '<pre id="xml-content" class="xml-content">' . $escapedXml . '</pre>';

            // Add JavaScript for copy and download functionality
            $html .= '<script>
                function copyXmlToClipboard(elementId) {
                    var xmlContent = document.getElementById(elementId);
                    
                    // Create a range and select the text
                    var range = document.createRange();
                    range.selectNode(xmlContent);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    
                    // Copy the text
                    try {
                        document.execCommand("copy");
                        // Show a temporary success message
                        var btn = document.getElementById("copy-xml-btn");
                        var originalText = btn.textContent;
                        btn.textContent = "Copied!";
                        setTimeout(function() {
                            btn.textContent = originalText;
                        }, 2000);
                    } catch (err) {
                        console.error("Unable to copy", err);
                    }
                    
                    // Clear selection
                    window.getSelection().removeAllRanges();
                }
                
                function downloadXml(xmlContent, filename) {
                    // Decode the escaped XML
                    var xml = xmlContent.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, "\"").replace(/&amp;/g, "&");
                    
                    // Create blob and download
                    var blob = new Blob([xml], {type: "text/xml"});
                    var a = document.createElement("a");
                    a.href = URL.createObjectURL(blob);
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                }
            </script>';

            $html .= '</div>';

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
}
