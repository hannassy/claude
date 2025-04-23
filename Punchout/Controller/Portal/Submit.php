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

class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly GetClient $getClient,
        private readonly SessionFactory $sessionFactory,
        private readonly Monolog $logger,
        private readonly MessageManager $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $buyerCookie = $this->request->getParam('cookie');
        $addressId = $this->request->getParam('locationId');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            if (empty($buyerCookie)) {
                throw new LocalizedException(__('Missing buyer cookie parameter'));
            }

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

            // Redirect back to portal with error
            return $resultRedirect->setPath('punchout/portal', [
                'cookie' => $buyerCookie
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error in portal submit: ' . $e->getMessage());

            $this->messageManager->addErrorMessage($e->getMessage());

            // Redirect back to portal with generic error
            return $resultRedirect->setPath('punchout/portal', [
                'cookie' => $buyerCookie
            ]);
        }
    }
}
