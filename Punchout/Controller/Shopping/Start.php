<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Shopping;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Tirehub\Punchout\Service\GetClient;
use Tirehub\Punchout\Model\SessionFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Start implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly GetClient $getClient,
        private readonly SessionFactory $sessionFactory,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute()
    {
        $customerId = $this->customerSession->getId();
        if ($customerId) {
            $this->customerSession->logout()->setLastCustomerId($customerId);
        }

        $buyerCookie = $this->request->getParam('cookie');

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

        return $resultRedirect->setPath('/');
    }
}
