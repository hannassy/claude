<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Shopping;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\Process\ShoppingStart as ShoppingStartProcess;
use Magento\Framework\View\Result\PageFactory;
use Exception;

class Start extends Action implements HttpGetActionInterface
{
    public const TOKEN_PARAM = 'token';

    public function __construct(
        Context $context,
        private readonly RequestInterface $request,
        private readonly ShoppingStartProcess $shoppingStartProcess,
        private readonly SessionFactory $sessionFactory,
        private readonly CustomerSession $customerSession,
        private readonly DisablePunchoutModeInterface $disablePunchoutMode,
        private readonly EncryptorInterface $encryptor,
        private readonly Monolog $logger,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->disablePunchoutMode->execute();

        // TODO clear cart

        try {
            // Force customer logout before validating
            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
                $this->customerSession->regenerateId();
                // Clear customer data from session
                $this->customerSession->clearStorage();
            }

            // Get and validate the token/cookie
            $buyerCookie = $this->validateAndGetBuyerCookie();

            if ($buyerCookie) {
                $session = $this->sessionFactory->create();
                $session->load($buyerCookie, 'buyer_cookie');

                if ($session->getId()) {
                    // Process the punchout session, but don't redirect yet
                    $hasItems = $this->shoppingStartProcess->execute($this->request);

                    // Store if items were added to the cart
                    $this->customerSession->setData('punchout_has_items', $hasItems);

                    return $this->pageFactory->create();
                }
            }

            // Fallback to home page if no valid cookie
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('/customer/account');
        } catch (LocalizedException|Exception $e) {
            $this->logger->error('Punchout: Error in shopping start: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());

            // Redirect to access denied or home page
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('');
        }
    }

    /**
     * Validate the request and get the buyer cookie
     */
    private function validateAndGetBuyerCookie(): ?string
    {
        // Code for validating token remains the same...
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

                $this->logger->info('Punchout: Successfully validated secure token for shopping start', [
                    'buyer_cookie' => $buyerCookie
                ]);

                // Store the buyer cookie as request parameter for downstream processing
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
            throw new LocalizedException(__('Invalid request parameters'));
        }

        // If using the old cookie parameter, verify the session exists
        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

        if (!$session->getId()) {
            throw new LocalizedException(__('Invalid session'));
        }

        $this->logger->info('Punchout: Using legacy cookie parameter for shopping start', [
            'buyer_cookie' => $buyerCookie
        ]);

        return $buyerCookie;
    }
}
