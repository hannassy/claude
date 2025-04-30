<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Portal;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Service\GetClient;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Magento\Framework\Message\Manager as MessageManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Session\SessionManagerInterface;
use Tirehub\Punchout\Service\TokenGenerator;

class Submit extends Action implements HttpPostActionInterface
{
    public const TOKEN_PARAM = 'token';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly GetClient $getClient,
        private readonly SessionFactory $sessionFactory,
        private readonly Monolog $logger,
        private readonly EncryptorInterface $encryptor,
        private readonly SessionManagerInterface $session,
        private readonly TokenGenerator $tokenGenerator,
        Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $addressId = $this->request->getParam('locationId');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            // Get and validate buyer cookie from token or legacy param
            $buyerCookie = $this->validateAndGetBuyerCookie();

            if (empty($addressId)) {
                throw new LocalizedException(__('Please select an address'));
            }

            // Load session
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid session'));
            }

            // Process the address submission through the client
            $client = $this->getClient->execute();
            return $client->processPortalAddressSubmit($this->request);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error in portal submit: ' . $e->getMessage());

            $this->messageManager->addErrorMessage($e->getMessage());

            // Generate secure redirect back to portal
            if (isset($buyerCookie) && !empty($buyerCookie)) {
                try {
                    $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);
                    return $resultRedirect->setUrl($portalUrl);
                } catch (\Exception $ex) {
                    $this->logger->error('Punchout: Error generating secure portal URL: ' . $ex->getMessage());
                }
            }

            // Fallback to simple redirect if token generation fails
            return $resultRedirect->setPath('punchout/portal');
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal submit: ' . $e->getMessage());

            $this->messageManager->addErrorMessage($e->getMessage());

            // Generate secure redirect back to portal
            if (isset($buyerCookie) && !empty($buyerCookie)) {
                try {
                    $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);
                    return $resultRedirect->setUrl($portalUrl);
                } catch (\Exception $ex) {
                    $this->logger->error('Punchout: Error generating secure portal URL: ' . $ex->getMessage());
                }
            }

            // Fallback to simple redirect if token generation fails
            return $resultRedirect->setPath('punchout/portal');
        }
    }

    /**
     * Validate the request and get the buyer cookie
     *
     * @return string
     * @throws LocalizedException
     */
    private function validateAndGetBuyerCookie(): string
    {
        // Check for the encrypted token first
        $token = $this->request->getParam(self::TOKEN_PARAM);
        if (!empty($token)) {
            try {
                $decrypted = $this->encryptor->decrypt(base64_decode($token));
                $tokenData = json_decode($decrypted, true);

                if (!isset($tokenData['cookie']) || !isset($tokenData['timestamp'])) {
                    throw new LocalizedException(__('Invalid token format'));
                }

                // Validate token is not expired (30 min max)
                $timestamp = (int)$tokenData['timestamp'];
                if (time() - $timestamp > 1800) {
                    throw new LocalizedException(__('Token expired'));
                }

                $buyerCookie = $tokenData['cookie'];

                $this->logger->info('Punchout: Successfully validated secure token for portal submit', [
                    'buyer_cookie' => $buyerCookie
                ]);

                // Add the cookie to request for downstream processing
                $this->request->setParam('cookie', $buyerCookie);

                return $buyerCookie;

            } catch (\Exception $e) {
                $this->logger->error('Punchout: Token validation error: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid or expired token'));
            }
        }

        // For backward compatibility, check if cookie parameter exists
        $buyerCookie = $this->request->getParam('cookie');
        if (empty($buyerCookie)) {
            // Last resort, check session storage
            $buyerCookie = $this->session->getData('punchout_buyer_cookie');
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }
        }

        // Verify the session exists
        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

        if (!$session->getId()) {
            throw new LocalizedException(__('Invalid session'));
        }

        $this->logger->info('Punchout: Using cookie parameter for portal submit', [
            'buyer_cookie' => $buyerCookie
        ]);

        return $buyerCookie;
    }
}
