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

class PortalAddressSubmit
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly SessionFactory $sessionFactory,
        private readonly SessionResource $sessionResource,
        private readonly CreateCustomerInterface $createCustomer,
        private readonly Monolog $logger
    ) {
    }

    /**
     * Process portal address submission
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(RequestInterface $request): \Magento\Framework\Controller\Result\Redirect
    {
        $buyerCookie = $request->getParam('cookie');
        $addressId = $request->getParam('locationId');

        try {
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }

            if (empty($addressId)) {
                throw new LocalizedException(__('Missing address_id parameter'));
            }

            // Load session
            $session = $this->sessionFactory->create();
            $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

            if (!$session->getId()) {
                throw new LocalizedException(__('Invalid buyer cookie'));
            }

            // Get extrinsics from session
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

            // Create customer using the selected address ID
            $customerId = $this->createCustomer->execute($extrinsics, $addressId);

            // Update session with customer ID
            $session->setData(SessionInterface::CUSTOMER_ID, $customerId);
            $session->setData(SessionInterface::ADDRESS_ID, $addressId);
            $this->sessionResource->save($session);

            // Redirect to shopping start
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/shopping/start', ['cookie' => $buyerCookie]);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing portal address submit: ' . $e->getMessage());

            // Redirect back to portal with error
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/portal', [
                'cookie' => $buyerCookie
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal address submit: ' . $e->getMessage());

            // Redirect back to portal with error
            $result = $this->redirectFactory->create();
            return $result->setPath('punchout/portal', [
                'cookie' => $buyerCookie,
                'error' => 'Unexpected error occurred'
            ]);
        }
    }
}
