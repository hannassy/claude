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
    private const CLASSIFICATION_DOMAIN = 'UNSPSC';

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

            $corpAddressId = $session->getData(SessionInterface::CORP_ADDRESS_ID);
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

            $partner = $this->getPartner($corpAddressId);

            if (!$partner) {
                $log->logWarning('Partner not found for corp address', [
                    'corp_address_id' => $corpAddressId
                ], $buyerCookie);
            }

            $cxml = $this->generateCxml($order, $session, $partner);

            $formData = [
                rawurlencode($cxml),
                $browserFormPostUrl
            ];

            $log->logInfo('Generated cXML response document', [
                'cxml_length' => strlen($cxml),
                'cxml_urlencoded_length' => strlen($formData[0]),
                'browser_form_post_url' => $browserFormPostUrl,
                'partner_identity' => $partner['identity'] ?? 'unknown',
                'partner_domain' => $partner['domain'] ?? 'unknown'
            ], $buyerCookie);

            $log->logDebug('Complete cXML response data', [
                'cxml_document' => $cxml,
                'cxml_urlencoded' => $formData[0],
                'form_data' => $formData
            ], $buyerCookie);

            try {
                $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_COMPLETED);
                $session->setData('cxml_response', $cxml);
                $session->setData('cxml_response_urlencoded', $formData[0]);
                $session->setData('cxml_response_base64', base64_encode($cxml));

                if ($order->getId()) {
                    $session->setData(SessionInterface::ERP_ORDER_NUMBER, $order->getErpOrderNumber());
                }

                $this->sessionResource->save($session);

                $log->logInfo('Session updated with response data', [
                    'session_id' => $session->getId(),
                    'erp_order_number' => $order->getErpOrderNumber(),
                    'cxml_response_saved' => true
                ], $buyerCookie);

                $this->disablePunchoutMode->execute();
                $this->customerSession->logout();

                return $formData;
            } catch (\Exception $e) {
                $this->logger->error("Punchout: Error updating session status: {$e->getMessage()}");

                $log->logError('Error updating session status', [
                    'error' => $e->getMessage()
                ], $buyerCookie);

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

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><!DOCTYPE cXML SYSTEM "http://xml.cXML.org/schemas/cXML/1.2.041/cXML.dtd"><cXML></cXML>');

        $xml->addAttribute('payloadID', $payloadId);
        $xml->addAttribute('timestamp', $currentDate);

        $header = $xml->addChild('Header');

        $from = $header->addChild('From');
        $fromCredential = $from->addChild('Credential');
        $fromCredential->addAttribute('domain', 'DUNS');
        $fromCredential->addChild('Identity', '08-125-4817');

        $to = $header->addChild('To');
        $toCredential = $to->addChild('Credential');
        $toCredential->addAttribute('domain', 'DUNS');
        $toCredential->addChild('Identity', $partner['identity'] ?? '');

        $sender = $header->addChild('Sender');
        $senderCredential = $sender->addChild('Credential');
        $senderCredential->addAttribute('domain', 'DUNS');
        $senderCredential->addChild('Identity', '08-125-4817');
        if ($partner && isset($partner['sharedSecret'])) {
            $senderCredential->addChild('SharedSecret', $partner['sharedSecret']);
        }
        $sender->addChild('UserAgent', 'TireHub Transactional Middleware');

        $message = $xml->addChild('Message');
        $message->addAttribute('deploymentMode', 'production');

        $punchOutOrderMessage = $message->addChild('PunchOutOrderMessage');
        $punchOutOrderMessage->addChild('BuyerCookie', $punchoutSession->getData('buyer_cookie'));

        $punchOutOrderMessageHeader = $punchOutOrderMessage->addChild('PunchOutOrderMessageHeader');
        $punchOutOrderMessageHeader->addAttribute('operationAllowed', 'create');

        $total = $punchOutOrderMessageHeader->addChild('Total');
        $totalMoney = $total->addChild('Money', number_format((float)$order->getGrandTotal(), 2, '.', ''));
        $totalMoney->addAttribute('currency', $order->getQuoteCurrencyCode() ?: 'USD');

        $temppo = $punchoutSession->getData('temppo');
        if (!$temppo) {
            throw new \Exception('Missing temppo in session data');
        }

        $lineNumber = 1;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $itemIn = $punchOutOrderMessage->addChild('ItemIn');
            $itemIn->addAttribute('quantity', (string)(int)$item->getQtyOrdered());
            $itemIn->addAttribute('lineNumber', (string)$lineNumber);

            $itemId = $itemIn->addChild('ItemID');
            $itemId->addChild('SupplierPartID', $item->getSku());
            $itemId->addChild('SupplierPartAuxiliaryID', $temppo);

            $itemDetail = $itemIn->addChild('ItemDetail');

            $unitPrice = $itemDetail->addChild('UnitPrice');
            $unitPriceMoney = $unitPrice->addChild('Money', number_format($item->getPrice(), 2, '.', ''));
            $unitPriceMoney->addAttribute('currency', $order->getQuoteCurrencyCode() ?: 'USD');

            $itemDetail->addChild('Description', $item->getName());

            $itemDetail->addChild('UnitOfMeasure', 'EA');

            $itemDetail->addChild('ManufacturerPartID', $item->getSku());

            $brand = $this->getProductBrandService->execute($item->getProduct());
            $itemDetail->addChild('ManufacturerName', $brand);

            $classification = $itemDetail->addChild('Classification', self::CLASSIFICATION);
            $classification->addAttribute('domain', self::CLASSIFICATION_DOMAIN);

            $lineNumber++;
        }

        return $xml->asXML();
    }
}
