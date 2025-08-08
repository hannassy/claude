<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Adminhtml\Session;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Tirehub\Punchout\Model\SessionFactory;
use Magento\Framework\Escaper;

class GetCxmlResponse extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_Punchout::session';

    private $resultJsonFactory;
    private $sessionFactory;
    private $escaper;

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

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $result = $this->resultJsonFactory->create();

        try {
            $session = $this->sessionFactory->create()->load($id);

            if (!$session->getId()) {
                throw new \Exception(__('Session not found')->getText());
            }

            $cxmlResponse = $session->getData('cxml_response');
            $cxmlUrlencoded = $session->getData('cxml_response_urlencoded');
            $cxmlBase64 = $session->getData('cxml_response_base64');

            if (empty($cxmlResponse) && empty($cxmlUrlencoded) && empty($cxmlBase64)) {
                throw new \Exception(__('No cXML response data available for this session')->getText());
            }

            $html = '<div class="xml-container">';
            $html .= '<div class="xml-header">';
            $html .= '<div class="xml-title">cXML Response for Session #' . $this->escaper->escapeHtml($id) . '</div>';
            $html .= '<div class="xml-details">';
            $html .= '<div><strong>Partner:</strong> ' . $this->escaper->escapeHtml($session->getData('partner_identity')) . '</div>';
            $html .= '<div><strong>Date:</strong> ' . $this->escaper->escapeHtml($session->getData('updated_at')) . '</div>';
            $html .= '<div><strong>Browser Form Post URL:</strong> ' . $this->escaper->escapeHtml($session->getData('browser_form_post_url')) . '</div>';
            $html .= '</div></div>';

            $html .= '<div class="xml-actions">';
            if ($cxmlResponse) {
                $formattedXml = $this->formatXml($cxmlResponse);
                $html .= '<button type="button" class="action-secondary" onclick="copyXmlToClipboard(\'xml-content\')">Copy XML</button>';
                $html .= '<button type="button" class="action-secondary" onclick="downloadXml(\'' . $this->escaper->escapeHtml(addslashes($formattedXml)) . '\', \'cxml-response-' . $id . '.xml\')">Download XML</button>';
            }
            $html .= '</div>';

            $html .= '<div class="xml-tabs">';
            $html .= '<div class="tab-buttons">';
            if ($cxmlResponse) {
                $html .= '<button class="tab-button active" onclick="showTab(\'xml-tab\')">XML Document</button>';
            }
            if ($cxmlUrlencoded) {
                $html .= '<button class="tab-button' . (empty($cxmlResponse) ? ' active' : '') . '" onclick="showTab(\'urlencoded-tab\')">URL Encoded</button>';
            }
            if ($cxmlBase64) {
                $html .= '<button class="tab-button' . (empty($cxmlResponse) && empty($cxmlUrlencoded) ? ' active' : '') . '" onclick="showTab(\'base64-tab\')">Base64</button>';
            }
            $html .= '</div>';

            if ($cxmlResponse) {
                $escapedXml = $this->escaper->escapeHtml($this->formatXml($cxmlResponse));
                $html .= '<div id="xml-tab" class="tab-content active">';
                $html .= '<pre id="xml-content" class="xml-content">' . $escapedXml . '</pre>';
                $html .= '</div>';
            }

            if ($cxmlUrlencoded) {
                $html .= '<div id="urlencoded-tab" class="tab-content' . (empty($cxmlResponse) ? ' active' : '') . '">';
                $html .= '<pre class="xml-content">' . $this->escaper->escapeHtml($cxmlUrlencoded) . '</pre>';
                $html .= '</div>';
            }

            if ($cxmlBase64) {
                $html .= '<div id="base64-tab" class="tab-content' . (empty($cxmlResponse) && empty($cxmlUrlencoded) ? ' active' : '') . '">';
                $html .= '<pre class="xml-content">' . $this->escaper->escapeHtml($cxmlBase64) . '</pre>';
                $html .= '</div>';
            }

            $html .= '</div>';

            $html .= '<script>
                function copyXmlToClipboard(elementId) {
                    var xmlContent = document.getElementById(elementId);
                    
                    var range = document.createRange();
                    range.selectNode(xmlContent);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    
                    try {
                        document.execCommand("copy");
                        var btn = document.querySelector(".xml-actions button");
                        var originalText = btn.textContent;
                        btn.textContent = "Copied!";
                        setTimeout(function() {
                            btn.textContent = originalText;
                        }, 2000);
                    } catch (err) {
                        console.error("Unable to copy", err);
                    }
                    
                    window.getSelection().removeAllRanges();
                }
                
                function downloadXml(xmlContent, filename) {
                    var xml = xmlContent.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, "\"").replace(/&amp;/g, "&");
                    
                    var blob = new Blob([xml], {type: "text/xml"});
                    var a = document.createElement("a");
                    a.href = URL.createObjectURL(blob);
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                }

                function showTab(tabId) {
                    var tabs = document.querySelectorAll(".tab-content");
                    var buttons = document.querySelectorAll(".tab-button");
                    
                    tabs.forEach(function(tab) {
                        tab.classList.remove("active");
                    });
                    
                    buttons.forEach(function(button) {
                        button.classList.remove("active");
                    });
                    
                    document.getElementById(tabId).classList.add("active");
                    event.target.classList.add("active");
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

    private function formatXml(string $xml): string
    {
        try {
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml);
            return $dom->saveXML();
        } catch (\Exception $e) {
            return $xml;
        }
    }
}
