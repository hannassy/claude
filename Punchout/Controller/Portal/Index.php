<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Portal;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Session\SessionManagerInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const TOKEN_PARAM = 'token';

    public function __construct(
        Context $context,
        private readonly DisablePunchoutModeInterface $disablePunchoutMode,
        private readonly EncryptorInterface $encryptor,
        private readonly SessionFactory $sessionFactory,
        private readonly Monolog $logger,
        private readonly SessionManagerInterface $session
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->disablePunchoutMode->execute();

        try {
            // Validate the request is coming from the proper flow
            $this->validateRequest();

            return $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error in portal access: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());

            // Redirect to access denied or home page
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('');
        }
    }

    /**
     * Validate the request is legitimate
     *
     * @throws LocalizedException
     */
    private function validateRequest(): void
    {
        $request = $this->getRequest();

        // Get and validate the token
        $token = $request->getParam(self::TOKEN_PARAM);
        if (empty($token)) {
            // For backward compatibility, check if cookie parameter exists
            $buyerCookie = $request->getParam('cookie');
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Invalid request parameters'));
            }

            // If using the old cookie parameter, verify the session exists
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid session'));
            }

            // Store the buyer cookie in the session for use by the block
            $this->session->setData('punchout_buyer_cookie', $buyerCookie);

            $this->logger->info('Punchout: Using legacy cookie parameter for portal', [
                'buyer_cookie' => $buyerCookie
            ]);

            return;
        }

        // Decrypt the token
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

            // Validate the buyer cookie exists in the database
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid session'));
            }

            // Store the buyer cookie in the session for use by the block
            $this->session->setData('punchout_buyer_cookie', $buyerCookie);

            $this->logger->info('Punchout: Successfully validated secure token for portal', [
                'buyer_cookie' => $buyerCookie
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Punchout: Token validation error: ' . $e->getMessage());
            throw new LocalizedException(__('Invalid or expired token'));
        }
    }
}
