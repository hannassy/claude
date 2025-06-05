<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Tirehub\Punchout\Model\Session as PunchoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Tirehub\Catalog\Api\GetProductBrandServiceInterface;
use Tirehub\Punchout\Model\LogFactory;

class PlaceOrder
{
    private const CLASSIFICATION = '25172504';

    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly CustomerSession $customerSession,
        private readonly SessionFactory $sessionFactory,
        private readonly DisablePunchoutModeInterface $disablePunchoutMode,
        private readonly SessionResource $sessionResource,
        private readonly GetProductBrandServiceInterface $getProductBrandService,
        private readonly LogFactory $logFactory
    ) {
    }

    public function execute(Order $order): array
    {
        $buyerCookie = null;
        $log = $this->logFactory->create();

        try {
            $buyerCookie = $this->customerSession->getData('buyer_cookie');

            $log->logInfo('Processing punchout order', [
                'order_id' => $order->getId(),
                'erp_order_number' => $order->getErpOrderNumber(),
                'buyerCookie' => $buyerCookie
            ], $buyerCookie);

            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, 'buyer_cookie');

            // Get client type to determine formatting
            $corpAddressId = $session->getData(SessionInterface::CORP_ADDRESS_ID);

            // Get browser form post URL from session
            $browserFormPostUrl = $session->getData(SessionInterface::BROWSER_FORM_POST_URL);
            if (empty($browserFormPostUrl)) {
                $this->logger->error('Punchout: Missing browser_form_post_url in session');
                throw new \Exception('Missing browser_form_post_url');
            }

            $log->logInfo('Retrieved session data', [
                'session_id' => $session->getId(),
                'corp_address_id' => $corpAddressId,
                'browser_form_post_url' => $browserFormPostUrl
            ], $buyerCookie);

            // Get partner settings
            $partner = $this->getPartner($corpAddressId);

            if (!$partner) {
                $log->logWarning('Partner not found for corp address', [
                    'corp_address_id' => $corpAddressId
                ], $buyerCookie);
            }

            // Generate cXML document
            $cxml = $this->generateCxml($order, $session, $partner);

            $log->logInfo('Generated cXML document', [
                'cxml_length' => strlen($cxml)
            ], $buyerCookie);

            // Prepare browser post form data
            $formData = [
                'cxml-urlencoded' => rawurlencode($cxml),
                'cxml-base64' => base64_encode($cxml),
                'browser_form_post_url' => $browserFormPostUrl
            ];

            try {
                // Update session status to completed
                $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_COMPLETED);

                // Optionally link the order to the session for reference
                if ($order->getId()) {
                    $session->setData(SessionInterface::ERP_ORDER_NUMBER, $order->getErpOrderNumber());
                }

                // Save the updated session
                $this->sessionResource->save($session);

                $log->logInfo('Session updated to completed status', [
                    'session_id' => $session->getId(),
                    'erp_order_number' => $order->getErpOrderNumber()
                ], $buyerCookie);

                $this->disablePunchoutMode->execute();

                // Log out the customer
                $this->customerSession->logout();

                $log->logInfo('Punchout session completed, customer logged out', [], $buyerCookie);

                $this->logger->info("Punchout: Session {$session->getId()} marked as completed for order {$order->getErpOrderNumber()}");

                return $formData;
            } catch (\Exception $e) {
                $this->logger->error("Punchout: Error updating session status: {$e->getMessage()}");

                $log->logError('Error updating session status', [
                    'error' => $e->getMessage()
                ], $buyerCookie);

                // Still return form data even if status update fails
                return $formData;
            }
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error generating order message: ' . $e->getMessage());

            $log->logCritical('Error generating order message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->getId() ?? null
            ], $buyerCookie);

            throw $e;
        }
    }

    private function getPartner(string $corpAddressId): ?array
    {
        $partners = $this->getPunchoutPartnersManagement->getResult();

        foreach ($partners as $partner) {
            $itemCorpAddressId = strtolower($partner['corpAddressId'] ?? '');
            if ($itemCorpAddressId === strtolower($corpAddressId)) {
                return $partner;
            }
        }

        return null;
    }

    private function generateCxml(Order $order, PunchoutSession $punchoutSession, ?array $partner): string
    {
        $currentDate = $this->timezone->date()->format('Y-m-d\TH:i:s.uP');
        $payloadId = uniqid() . '@tirehub';

        // Format XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><!DOCTYPE cXML SYSTEM "http://xml.cXML.org/schemas/cXML/1.2.041/cXML.dtd"><cXML></cXML>');

        // Add cXML attributes
        $xml->addAttribute('payloadID', $payloadId);
        $xml->addAttribute('timestamp', $currentDate);

        // Add header
        $header = $xml->addChild('Header');

        // From section
        $from = $header->addChild('From');
        $fromCredential = $from->addChild('Credential');
        $fromCredential->addAttribute('domain', 'DUNS');
        $fromCredential->addChild('Identity', '08-125-4817');

        // To section
        $to = $header->addChild('To');
        $toCredential = $to->addChild('Credential');
        $toCredential->addAttribute('domain', 'DUNS');
        $toCredential->addChild('Identity', $partner['identity'] ?? '');

        // Sender section
        $sender = $header->addChild('Sender');
        $senderCredential = $sender->addChild('Credential');
        $senderCredential->addAttribute('domain', 'DUNS');
        $senderCredential->addChild('Identity', '08-125-4817');
        if ($partner && isset($partner['sharedSecret'])) {
            $senderCredential->addChild('SharedSecret', $partner['sharedSecret']);
        }
        $sender->addChild('UserAgent', 'TireHub Transactional Middleware');

        // Add message
        $message = $xml->addChild('Message');
        $message->addAttribute('deploymentMode', 'production');

        $punchOutOrderMessage = $message->addChild('PunchOutOrderMessage');

        // Add buyer cookie
        $punchOutOrderMessage->addChild('BuyerCookie', $punchoutSession->getData('buyer_cookie'));

        // Add header with total
        $punchOutOrderMessageHeader = $punchOutOrderMessage->addChild('PunchOutOrderMessageHeader');
        $punchOutOrderMessageHeader->addAttribute('operationAllowed', 'create');

        $total = $punchOutOrderMessageHeader->addChild('Total');
        $totalMoney = $total->addChild('Money', number_format((float)$order->getGrandTotal(), 2, '.', ''));
        $totalMoney->addAttribute('currency', $order->getQuoteCurrencyCode() ?: 'USD');

        // Add items
        $lineNumber = 1;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue; // Skip child items of configurable/bundle products
            }

            $itemIn = $punchOutOrderMessage->addChild('ItemIn');
            $itemIn->addAttribute('quantity', (string)(int)$item->getQtyOrdered());
            $itemIn->addAttribute('lineNumber', (string)$lineNumber);

            // Add item ID
            $itemId = $itemIn->addChild('ItemID');
            $itemId->addChild('SupplierPartID', $item->getSku());

            // Add temppo if available
            $temppo = $punchoutSession->getData('temppo');
            if ($temppo) {
                $itemId->addChild('SupplierPartAuxiliaryID', $temppo);
            }

            // Add item detail
            $itemDetail = $itemIn->addChild('ItemDetail');

            // Add unit price
            $unitPrice = $itemDetail->addChild('UnitPrice');
            $unitPriceMoney = $unitPrice->addChild('Money', number_format($item->getPrice(), 2, '.', ''));
            $unitPriceMoney->addAttribute('currency', $order->getQuoteCurrencyCode() ?: 'USD');

            $brand = $this->getProductBrandService->execute($item->getProduct());

            $itemDetail->addChild('Description', $item->getName());
            $itemDetail->addChild('ManufacturerPartID', $item->getSku());
            $itemDetail->addChild('ManufacturerName', $brand);
            $itemDetail->addChild('Classification', self::CLASSIFICATION);

            $lineNumber++;
        }

        return $xml->asXML();
    }
}
