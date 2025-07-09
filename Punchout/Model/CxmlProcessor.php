<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Model\Validator\Credentials as CredentialsValidator;
use SimpleXMLElement;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Service\ExtractAddressId;

class CxmlProcessor
{
    public function __construct(
        private readonly Monolog $logger,
        private readonly CredentialsValidator $credentialsValidator,
        private readonly SessionFactory $sessionFactory,
        private readonly Config $config,
        private readonly ExtractAddressId $extractAddressId
    ) {
    }

    public function parseRequest(string $content): array
    {
        if (empty($content)) {
            $this->logger->error('Punchout: Empty XML content provided');
            throw new LocalizedException(__('Empty request content'));
        }

        try {
            $logContent = substr($content, 0, 500);
            $this->logger->info('Punchout: Processing XML content: ' . $logContent . (strlen($content) > 500 ? '...' : ''));

            $content = $this->sanitizeXmlContent($content);

            $xml = new SimpleXMLElement($content);

            $fromCredential = $xml->xpath('//Header/From/Credential');
            $toCredential = $xml->xpath('//Header/To/Credential');
            $senderCredential = $xml->xpath('//Header/Sender/Credential');

            if (empty($fromCredential) || empty($toCredential) || empty($senderCredential)) {
                throw new LocalizedException(__('Invalid cXML request: Missing credentials'));
            }

            $fromDomain = (string)$fromCredential[0]['domain'];
            $fromIdentity = (string)$fromCredential[0]->Identity;

            $toDomain = (string)$toCredential[0]['domain'];
            $toIdentity = (string)$toCredential[0]->Identity;

            $senderDomain = (string)$senderCredential[0]['domain'];
            $senderIdentity = (string)$senderCredential[0]->Identity;
            $senderSecret = (string)$senderCredential[0]->SharedSecret;

            $setupRequest = $xml->Request->PunchOutSetupRequest;
            if (empty($setupRequest)) {
                throw new LocalizedException(__('Invalid cXML request: Missing PunchOutSetupRequest'));
            }

            $buyerCookie = (string)$setupRequest->BuyerCookie;
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Invalid cXML request: Missing BuyerCookie'));
            }

            $this->validateBuyerCookieNotReused($buyerCookie, $senderIdentity);

            $extrinsics = [];
            foreach ($setupRequest->Extrinsic as $extrinsic) {
                $name = (string)$extrinsic['name'];
                $value = (string)$extrinsic;
                $extrinsics[$name] = $value;
            }

            $browserFormPostUrl = '';
            if (isset($setupRequest->BrowserFormPost) && isset($setupRequest->BrowserFormPost->URL)) {
                $browserFormPostUrl = (string)$setupRequest->BrowserFormPost->URL;
            }

            $addressId = null;
            if (isset($setupRequest->ShipTo->Address)) {
                $addressId = $this->extractAddressId($setupRequest->ShipTo->Address, $senderIdentity);
            }

            $result = [
                'from' => [
                    'domain' => $fromDomain,
                    'identity' => $fromIdentity
                ],
                'to' => [
                    'domain' => $toDomain,
                    'identity' => $toIdentity
                ],
                'sender' => [
                    'domain' => $senderDomain,
                    'identity' => $senderIdentity,
                    'shared_secret' => $senderSecret
                ],
                'buyer_cookie' => $buyerCookie,
                'extrinsics' => $extrinsics,
                'browser_form_post_url' => $browserFormPostUrl,
                'address_id' => $addressId
            ];

            if ($this->config->isDebugMode()) {
                $result['cxml_request'] = $content;
            }

            return $result;
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: XML parsing error: ' . $e->getMessage() . ' in content: ' . substr($content, 0, 500));
            throw new LocalizedException(__('Error parsing cXML request: %1', $e->getMessage()));
        }
    }

    private function validateBuyerCookieNotReused(string $buyerCookie, string $partnerIdentity): void
    {
        if ($this->config->isBuyerCookieValidationDisabled()) {
            $this->logger->warning(
                'Punchout: Buyer cookie validation is DISABLED in config (testing mode)',
                [
                    'buyer_cookie' => $buyerCookie,
                    'partner' => $partnerIdentity
                ]
            );
            return;
        }

        try {
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if ($session->getId()) {
                $sessionStatus = (int)$session->getData(SessionInterface::STATUS);

                if ($sessionStatus !== SessionInterface::STATUS_NEW) {
                    $this->logger->warning(
                        'Punchout: Attempt to reuse buyer cookie',
                        [
                            'buyer_cookie' => $buyerCookie,
                            'partner' => $partnerIdentity,
                            'session_status' => $sessionStatus
                        ]
                    );

                    throw new LocalizedException(
                        __('Security violation: This buyer cookie has already been used')
                    );
                }
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error validating buyer cookie: ' . $e->getMessage());
        }
    }

    private function sanitizeXmlContent(string $content): string
    {
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        $content = preg_replace('/^[\s\r\n]+/', '', $content);

        if (!preg_match('/^<\?xml/', $content)) {
            if (!str_contains($content, '<?xml')) {
                $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
            } else {
                preg_match('/<\?xml.*?\?>/', $content, $matches);
                if (isset($matches[0])) {
                    $content = $matches[0] . str_replace($matches[0], '', $content);
                }
            }
        }

        $content = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $content);

        return $content;
    }

    public function validateCredentials(string $domain, string $identity, string $sharedSecret): void
    {
        try {
            $this->credentialsValidator->execute($domain, $identity, $sharedSecret);
            $this->logger->info('Punchout: Successfully validated credentials for domain: ' . $domain);
        } catch (LocalizedException $e) {
            $this->logger->warning('Punchout: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateSuccessResponse(string $punchoutUrl): string
    {
        $payloadId = uniqid() . '@tirehub';
        $timestamp = date('Y-m-d\TH:i:s.uP');

        $responseXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><!DOCTYPE cXML SYSTEM "http://xml.cXML.org/schemas/cXML/1.2.041/cXML.dtd"><cXML></cXML>');

        $responseXml->addAttribute('payloadID', $payloadId);
        $responseXml->addAttribute('timestamp', $timestamp);

        $response = $responseXml->addChild('Response');

        $status = $response->addChild('Status');
        $status->addAttribute('code', '200');
        $status->addAttribute('text', 'success');

        $punchoutSetupResponse = $response->addChild('PunchOutSetupResponse');
        $startPage = $punchoutSetupResponse->addChild('StartPage');
        $startPage->addChild('URL', $punchoutUrl);

        return $responseXml->asXML();
    }

    public function generateErrorResponse(string $errorCode, string $errorMessage): string
    {
        $payloadId = uniqid() . '@tirehub';
        $timestamp = date('Y-m-d\TH:i:s.uP');

        $responseXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><!DOCTYPE cXML SYSTEM "http://xml.cXML.org/schemas/cXML/1.2.041/cXML.dtd"><cXML></cXML>');

        $responseXml->addAttribute('payloadID', $payloadId);
        $responseXml->addAttribute('timestamp', $timestamp);

        $response = $responseXml->addChild('Response');

        $status = $response->addChild('Status');
        $status->addAttribute('code', $errorCode);
        $status->addAttribute('text', $errorMessage);

        return $responseXml->asXML();
    }

    private function extractAddressId(SimpleXMLElement $addressNode, string $senderIdentity): ?string
    {
        $addressId = (string)$addressNode['addressID'] ?? '';
        if (!$addressId) {
            return null;
        }

        return $this->extractAddressId->execute($addressId, $senderIdentity);
    }

    public function generateInvalidDealerCodeResponse(string $dealerCode): string
    {
        $message = "Unable to match requested address id {$dealerCode} to TireHub Ship To! Please contact your administrator";
        return $this->generateErrorResponse('400', $message);
    }

    public function generateInvalidIdentityResponse(): string
    {
        return $this->generateErrorResponse('400', 'Unable to find identity match!');
    }

    public function generateInvalidSharedSecretResponse(): string
    {
        return $this->generateErrorResponse('401', 'Invalid shared secret!');
    }

    public function generateBuyerCookieReuseResponse(): string
    {
        return $this->generateErrorResponse('403', 'Security violation: This buyer cookie has already been used');
    }

    public function generateUnauthorizedDealerResponse(string $dealerCode): string
    {
        $message = "This location {$dealerCode} Is not currently authorized to use TireHub punchout! Please contact your administrator";
        return $this->generateErrorResponse('401', $message);
    }

    public function generateInvalidXmlResponse(): string
    {
        return $this->generateErrorResponse(
            '500',
            'The incoming cXml is not in a known format or is missing required attributes'
        );
    }
}
