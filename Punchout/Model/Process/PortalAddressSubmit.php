<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Api\CreateCustomerInterface;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Service\TokenGenerator;
use Tirehub\Punchout\Model\LogFactory;

class PortalAddressSubmit
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly SessionFactory $sessionFactory,
        private readonly SessionResource $sessionResource,
        private readonly CreateCustomerInterface $createCustomer,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Monolog $logger,
        private readonly LogFactory $logFactory
    ) {
    }

    public function execute(RequestInterface $request): Redirect
    {
        $buyerCookie = $request->getParam('cookie');
        $addressId = $request->getParam('locationId');
        $log = $this->logFactory->create();

        try {
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }

            if (empty($addressId)) {
                throw new LocalizedException(__('Missing address_id parameter'));
            }

            $log->logInfo('Processing portal address submit', [
                'buyerCookie' => $buyerCookie,
                'addressId' => $addressId
            ], $buyerCookie);

            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid buyer cookie'));
            }

            $extrinsics = [];
            if ($session->getData(SessionInterface::FULL_NAME)) {
                $extrinsics['UserFullName'] = $session->getData(SessionInterface::FULL_NAME);
            }
            if ($session->getData(SessionInterface::FIRST_NAME)) {
                $extrinsics['FirstName'] = $session->getData(SessionInterface::FIRST_NAME);
            }
            if ($session->getData(SessionInterface::LAST_NAME)) {
                $extrinsics['LastName'] = $session->getData(SessionInterface::LAST_NAME);
            }
            if ($session->getData(SessionInterface::PHONE)) {
                $extrinsics['PhoneNumber'] = $session->getData(SessionInterface::PHONE);
            }

            $log->logInfo('Creating customer', [
                'addressId' => $addressId,
                'has_extrinsics' => !empty($extrinsics)
            ], $buyerCookie);

            $customerId = $this->createCustomer->execute($extrinsics, $addressId);

            $session->setData(SessionInterface::CUSTOMER_ID, $customerId);
            $session->setData(SessionInterface::ADDRESS_ID, $addressId);
            $this->sessionResource->save($session);

            $log->logInfo('Customer created successfully', [
                'customer_id' => $customerId,
                'session_id' => $session->getId()
            ], $buyerCookie);

            $shoppingUrl = $this->tokenGenerator->generateShoppingStartUrl($buyerCookie);

            $log->logInfo('Redirecting to shopping', [
                'redirect_url' => $shoppingUrl
            ], $buyerCookie);

            $result = $this->redirectFactory->create();
            return $result->setUrl($shoppingUrl);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing portal address submit: ' . $e->getMessage());

            $log->logError('Error processing portal address submit', [
                'error' => $e->getMessage(),
                'addressId' => $addressId ?? ''
            ], $buyerCookie);

            $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);

            $result = $this->redirectFactory->create();
            return $result->setUrl($portalUrl);
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal address submit: ' . $e->getMessage());

            $log->logCritical('Unexpected error in portal address submit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], $buyerCookie);

            $portalUrl = $this->tokenGenerator->generatePortalUrl($buyerCookie);

            $result = $this->redirectFactory->create();
            return $result->setUrl($portalUrl);
        }
    }
}
