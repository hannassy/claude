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
            // Log the first part of content for debugging (limit to avoid huge logs)
            $logContent = substr($content, 0, 500);
            $this->logger->info('Punchout: Processing XML content: ' . $logContent . (strlen($content) > 500 ? '...' : ''));

            // Try to sanitize XML before parsing
            $content = $this->sanitizeXmlContent($content);

            // Attempt to create SimpleXMLElement
            $xml = new SimpleXMLElement($content);

            // Validate request
            $fromCredential = $xml->xpath('//Header/From/Credential');
            $toCredential = $xml->xpath('//Header/To/Credential');
            $senderCredential = $xml->xpath('//Header/Sender/Credential');

            if (empty($fromCredential) || empty($toCredential) || empty($senderCredential)) {
                throw new LocalizedException(__('Invalid cXML request: Missing credentials'));
            }

            // Extract credentials
            $fromDomain = (string)$fromCredential[0]['domain'];
            $fromIdentity = (string)$fromCredential[0]->Identity;

            $toDomain = (string)$toCredential[0]['domain'];
            $toIdentity = (string)$toCredential[0]->Identity;

            $senderDomain = (string)$senderCredential[0]['domain'];
            $senderIdentity = (string)$senderCredential[0]->Identity;
            $senderSecret = (string)$senderCredential[0]->SharedSecret;

            // Extract setup request data
            $setupRequest = $xml->Request->PunchOutSetupRequest;
            if (empty($setupRequest)) {
                throw new LocalizedException(__('Invalid cXML request: Missing PunchOutSetupRequest'));
            }

            $buyerCookie = (string)$setupRequest->BuyerCookie;
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Invalid cXML request: Missing BuyerCookie'));
            }

            // Check if this buyer cookie is already used with a different partner identity
            $this->validateBuyerCookieNotReused($buyerCookie, $senderIdentity);

            // Extract extrinsic data
            $extrinsics = [];
            foreach ($setupRequest->Extrinsic as $extrinsic) {
                $name = (string)$extrinsic['name'];
                $value = (string)$extrinsic;
                $extrinsics[$name] = $value;
            }

            // Browser form post URL
            $browserFormPostUrl = '';
            if (isset($setupRequest->BrowserFormPost) && isset($setupRequest->BrowserFormPost->URL)) {
                $browserFormPostUrl = (string)$setupRequest->BrowserFormPost->URL;
            }

            // Ship to address
            $addressId = null;
            if (isset($setupRequest->ShipTo->Address)) {
                $addressId = $this->extractAddressId($setupRequest->ShipTo->Address, $senderIdentity);
            }

            // Return parsed data
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

            // If debug mode is enabled, store the raw cXML request
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

    /**
     * Validate that the buyer cookie is not being reused with a different partner identity
     *
     * @param string $buyerCookie
     * @param string $partnerIdentity
     * @throws LocalizedException
     */
    private function validateBuyerCookieNotReused(string $buyerCookie, string $partnerIdentity): void
    {
        try {
            // Check if a session with this buyer cookie already exists
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            // If session exists and has already been processed, reject the request
            if ($session->getId()) {
                $sessionStatus = (int)$session->getData(SessionInterface::STATUS);

                // If session is not in 'NEW' status, it's already been processed
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
            // Continue processing if there's an unexpected error checking the session
        }
    }

    private function sanitizeXmlContent(string $content): string
    {
        // Remove UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Remove any leading whitespace before XML declaration
        $content = preg_replace('/^[\s\r\n]+/', '', $content);

        // Make sure XML declaration is at the beginning
        if (!preg_match('/^<\?xml/', $content)) {
            // If no XML declaration, add one
            if (strpos($content, '<?xml') === false) {
                $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
            } // If XML declaration exists but not at the beginning, move it
            else {
                preg_match('/<\?xml.*?\?>/', $content, $matches);
                if (isset($matches[0])) {
                    $content = $matches[0] . str_replace($matches[0], '', $content);
                }
            }
        }

        // Replace invalid XML characters
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

    /**
     * Generate error response for invalid dealer code
     *
     * @param string $dealerCode The dealer code that failed validation
     * @return string XML response
     */
    public function generateInvalidDealerCodeResponse(string $dealerCode): string
    {
        $message = "Unable to match requested address id {$dealerCode} to TireHub Ship To! Please contact your administrator";
        return $this->generateErrorResponse('400', $message);
    }

    /**
     * Generate error response for invalid identity
     *
     * @return string XML response
     */
    public function generateInvalidIdentityResponse(): string
    {
        return $this->generateErrorResponse('400', 'Unable to find identity match!');
    }

    /**
     * Generate error response for invalid shared secret
     *
     * @return string XML response
     */
    public function generateInvalidSharedSecretResponse(): string
    {
        return $this->generateErrorResponse('401', 'Invalid shared secret!');
    }

    /**
     * Generate response for security violation with buyer cookie reuse
     *
     * @return string XML response
     */
    public function generateBuyerCookieReuseResponse(): string
    {
        return $this->generateErrorResponse('403', 'Security violation: This buyer cookie is already associated with a different partner');
    }

    /**
     * Generate error response for unauthorized dealer in pilot mode
     *
     * @param string $dealerCode The dealer code that is not authorized
     * @return string XML response
     */
    public function generateUnauthorizedDealerResponse(string $dealerCode): string
    {
        $message = "This location {$dealerCode} Is not currently authorized to use TireHub punchout! Please contact your administrator";
        return $this->generateErrorResponse('401', $message);
    }

    /**
     * Generate error response for XML parsing issues
     *
     * @return string XML response
     */
    public function generateInvalidXmlResponse(): string
    {
        return $this->generateErrorResponse(
            '500',
            'The incoming cXml is not in a known format or is missing required attributes'
        );
    }
}
