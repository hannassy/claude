<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Portal;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Session\SessionManagerInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\Process\PortalAddressSubmit as PortalAddressSubmitProcess;
use Tirehub\Punchout\Service\TokenGenerator;

class Submit extends Action implements HttpPostActionInterface
{
    public const TOKEN_PARAM = 'token';

    public function __construct(
        Context $context,
        private readonly RequestInterface $request,
        private readonly PortalAddressSubmitProcess $portalAddressSubmitProcess,
        private readonly SessionFactory $sessionFactory,
        private readonly Monolog $logger,
        private readonly EncryptorInterface $encryptor,
        private readonly SessionManagerInterface $session,
        private readonly TokenGenerator $tokenGenerator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            // Get and validate buyer cookie from token or legacy param
            $buyerCookie = $this->validateAndGetBuyerCookie();

            // At this point we have a validated buyer cookie, so pass it to the process
            // Make sure the request has the cookie parameter for the process
            $this->request->setParams(array_merge(
                $this->request->getParams(),
                ['cookie' => $buyerCookie]
            ));

            // Execute the address submission process
            $result = $this->portalAddressSubmitProcess->execute($this->request);

            // Ensure we're returning a Redirect object
            if ($result instanceof Redirect) {
                return $result;
            }

            // If the result is not a Redirect, convert it to one
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            if (method_exists($result, 'getUrl')) {
                return $resultRedirect->setUrl($result->getUrl());
            }

            // Default fallback
            return $resultRedirect->setPath('');
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error in portal submit: ' . $e->getMessage());

            $this->messageManager->addErrorMessage($e->getMessage());

            // Generate secure redirect back to portal
            if (isset($buyerCookie) && !empty($buyerCookie)) {
                try {
                    $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);
                    $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                    return $resultRedirect->setUrl($portalUrl);
                } catch (\Exception $ex) {
                    $this->logger->error('Punchout: Error generating secure portal URL: ' . $ex->getMessage());
                }
            }

            // Fallback to simple redirect if token generation fails
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('punchout/portal');
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal submit: ' . $e->getMessage());

            $this->messageManager->addErrorMessage($e->getMessage());

            // Generate secure redirect back to portal
            if (isset($buyerCookie) && !empty($buyerCookie)) {
                try {
                    $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);
                    $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                    return $resultRedirect->setUrl($portalUrl);
                } catch (\Exception $ex) {
                    $this->logger->error('Punchout: Error generating secure portal URL: ' . $ex->getMessage());
                }
            }

            // Fallback to simple redirect if token generation fails
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
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
        $token = $this->getRequest()->getParam(self::TOKEN_PARAM);
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

                return $buyerCookie;

            } catch (\Exception $e) {
                $this->logger->error('Punchout: Token validation error: ' . $e->getMessage());
                throw new LocalizedException(__('Invalid or expired token'));
            }
        }

        // For backward compatibility, check if cookie parameter exists
        $buyerCookie = $this->getRequest()->getParam('cookie');
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
