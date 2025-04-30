<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Shopping;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Tirehub\Punchout\Service\GetClient;
use Tirehub\Punchout\Model\SessionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Api\Data\SessionInterface;

class Start extends Action implements HttpGetActionInterface
{
    public const TOKEN_PARAM = 'token';

    public function __construct(
        Context $context,
        private RequestInterface $request,
        private GetClient $getClient,
        private SessionFactory $sessionFactory,
        private CustomerSession $customerSession,
        private DisablePunchoutModeInterface $disablePunchoutMode,
        private EncryptorInterface $encryptor,
        private Monolog $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->disablePunchoutMode->execute();

        try {
            // Get and validate the token/cookie
            $buyerCookie = $this->validateAndGetBuyerCookie();

            // If validation passed, proceed with original flow
            $customerId = $this->customerSession->getId();
            if ($customerId) {
                $this->customerSession->logout()->setLastCustomerId($customerId);
            }

            if ($buyerCookie) {
                $session = $this->sessionFactory->create();
                $session->load($buyerCookie, 'buyer_cookie');

                if ($session->getId()) {
                    $client = $this->getClient->execute();
                    return $client->processShoppingStart($this->request);
                }
            }

            // Fallback to home page if no valid cookie
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('/customer/account');

        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error in shopping start: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());

            // Redirect to access denied or home page
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('');
        }
    }

    /**
     * Validate the request and get the buyer cookie
     *
     * @return string|null
     * @throws LocalizedException
     */
    private function validateAndGetBuyerCookie(): ?string
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

                $this->logger->info('Punchout: Successfully validated secure token for shopping start', [
                    'buyer_cookie' => $buyerCookie
                ]);

                // Store the buyer cookie as request parameter for downstream processing
                // This is needed because other code expects it as a request parameter
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
