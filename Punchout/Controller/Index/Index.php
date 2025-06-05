<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Index;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Validator\ValidateException;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\Manager as MessageManager;
use Tirehub\Punchout\Model\ItemFactory as RequestFactory;
use Tirehub\Punchout\Api\RequestStatusInterface;
use \Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ActionInterface;

class Index implements ActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    private array $partnerData = [];

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        private readonly Http $http,
        private readonly RequestInterface $request,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly RequestFactory $requestFactory
    ) {
    }

    public function execute()
    {
        try {
            $this->validateParams();
            $this->validatePartner();
            $this->validateDealer();
            $this->saveRequest();
            $this->redirectToPunchoutUrl();
        } catch (ValidateException $e) {
            // redirect to login page with error message
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical('Punchout validation error:', [
                'params' => $this->request->getParams(),
                'message' => $e->getMessage()
            ]);

            $redirect = $this->redirectFactory->create();
            $redirect->setPath('b2b/portal/login', ['access' => 'denied']);
        } catch (Exception $e) {
            $this->logger->critical('Punchout error:', [
                'params' => $this->request->getParams(),
                'message' => $e->getMessage()
            ]);
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('b2b/portal/login', ['access' => 'denied']);
        }
    }

    /**
     * @throws ValidateException
     */
    private function validateParams(): void
    {
        $partnerIdentity = $this->request->getParam('partnerIdentity');
        $dealerCode = $this->request->getParam('dealerCode');
        if (!$partnerIdentity || !$dealerCode) {
            throw new ValidateException('Partner Identity or Dealer Code is required.');
        }
    }

    private function validateDealer(): void
    {
        $dealerCode = $this->request->getParam('dealerCode');
        $corpAddressId = $this->partnerData['corpAddressId'] ?? null;
        if (!$corpAddressId) {
            throw new ValidateException('corpAddressId is required.');
        }

        if ($dealerCode != $corpAddressId) {
            throw new ValidateException('dealerCode is different from corpAddressId.');
        }

        // TODO search user by dealerCode, if not found - create user
    }

    /**
     * @throws ValidateException
     */
    private function validatePartner(): void
    {
        $partnerIdentity = (string)$this->request->getParam('partnerIdentity');
        $partnerIdentity = strtolower(trim($partnerIdentity));
        $punchoutPartners = $this->getPunchoutPartnersManagement->getResult();
        foreach ($punchoutPartners as $punchoutPartner) {
            $identity = (string)($punchoutPartner['identity'] ?? '');
            if (!$identity) {
                continue;
            }

            $identity = strtolower($identity);
            if ($identity == $partnerIdentity) {
                $this->partnerData = $punchoutPartner;
                break;
            }
        }

        if (!$this->partnerData) {
            throw new ValidateException(
                __('Partner Identity "%1" is not set up', $partnerIdentity)->getText()
            );
        }
    }

    private function saveRequest(): void
    {
        $sharedSecret = $this->partnerData['sharedSecret'] ?? '';
        if (!$sharedSecret) {
            throw new ValidateException('Shared Secret is required.');
        }
        $this->partnerData['itemId'] = $this->request->getParam('itemId');
        $this->partnerData['quantityNeeded'] = $this->request->getParam('quantityNeeded');

        $request = $this->requestFactory->create();
        $request->setData('indentity', $this->partnerData['identity']);
        $request->setData('dealer_code', $this->request->getParam('dealerCode'));
        $request->setData('shared_secret', $sharedSecret);
        $request->setData('item_id', $this->request->getParam('itemId'));
        $request->setData('quantity_needed', $this->request->getParam('quantityNeeded'));
        $request->setData('status', RequestStatusInterface::INITIALIZED);
        $request->setData('session_key', 'TODO');
        $request->save();
    }

    /**
     * @throws ValidateException
     */
    private function redirectToPunchoutUrl(): void
    {
        $punchoutRedirectUrl = $this->partnerData['punchoutRedirectUrl'] ?? '';
        if (!$punchoutRedirectUrl) {
            throw new ValidateException('Punchout Redirect Url is required.');
        }

        $redirect = $this->redirectFactory->create();

        // TODO pass some params
        $redirect->setPath($punchoutRedirectUrl, []);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }
}
