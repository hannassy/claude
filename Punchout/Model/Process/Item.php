<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\ItemFactory;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Tirehub\Punchout\Service\ExtractAddressId;
use Magento\Framework\Logger\Monolog;

class Item
{
    private bool $debugItemRedirectUrl = true;

    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly SessionFactory $sessionFactory,
        private readonly SessionResource $sessionResource,
        private readonly ItemFactory $itemFactory,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly ExtractAddressId $extractAddressId,
        private readonly Monolog $logger
    ) {
    }

    /**
     * Process item request for punchout
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function execute(RequestInterface $request): ResultInterface
    {
        try {
            // Get required parameters
            $partnerIdentity = $request->getParam('partnerIdentity');
            $dealerCode = $request->getParam('dealerCode');
            $itemId = $request->getParam('itemId');
            $quantityNeeded = (int)$request->getParam('quantityNeeded', 1);

            // Validate required parameters
            if (empty($partnerIdentity) || empty($dealerCode)) {
                throw new LocalizedException(__('Partner Identity and Dealer Code are required parameters'));
            }

            $this->logger->info('Punchout: Processing item request', [
                'partnerIdentity' => $partnerIdentity,
                'dealerCode' => $dealerCode,
                'itemId' => $itemId,
                'quantityNeeded' => $quantityNeeded
            ]);

            // Validate dealer code
            $dealerCode = $this->extractAddressId->execute($dealerCode, $partnerIdentity);
            if (!$dealerCode) {
                throw new LocalizedException(__('Invalid dealer code or partner identity'));
            }

            // Generate a unique buyerCookie (token) for this punchout session
            // This will be used as the buyerCookie in subsequent steps
            $buyerCookie = md5($partnerIdentity . $dealerCode . uniqid('', true));

            // Check if we have multiple items (comma-separated)
            $itemIds = $itemId ? explode(',', $itemId) : [];

            if (!empty($itemIds)) {
                // Process each item
                foreach ($itemIds as $singleItemId) {
                    // Handle each item
                    $this->saveItemRequest($buyerCookie, $dealerCode, $partnerIdentity, $singleItemId, $quantityNeeded);
                }
            }

            $corpAddressId = $this->getCorpAddressId($partnerIdentity);

            // Create a new punchout session
            $session = $this->sessionFactory->create();
            $session->setData(SessionInterface::BUYER_COOKIE, $buyerCookie);
            $session->setData(SessionInterface::CLIENT_TYPE, 'default');
            $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_NEW);
            $session->setData(SessionInterface::PARTNER_IDENTITY, $partnerIdentity);
            $session->setData(SessionInterface::CORP_ADDRESS_ID, $corpAddressId);
            $session->setData(SessionInterface::ADDRESS_ID, $dealerCode);
            $this->sessionResource->save($session);

            // Get redirect URL from partner settings
            $redirectUrl = $this->getRedirectUrl($partnerIdentity);

            if (empty($redirectUrl) || $this->debugItemRedirectUrl) {
                // If no redirect URL is configured, return success response
                $result = $this->rawFactory->create();
                $result->setHttpResponseCode(200);
                $result->setContents(json_encode(['success' => true, 'buyerCookie' => $buyerCookie]));
                $result->setHeader('Content-Type', 'application/json');
                return $result;
            }

            // Add cookie parameter to redirect URL
            $separator = (str_contains($redirectUrl, '?')) ? '&' : '?';
            $redirectUrl .= $separator . 'cookie=' . $buyerCookie;

            // Redirect to partner URL
            $resultRedirect = $this->redirectFactory->create();
            return $resultRedirect->setUrl($redirectUrl);
        } catch (LocalizedException $e) {
            $this->logger->error('Punchout: Error processing item request: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(400);
            $result->setContents(json_encode(['error' => $e->getMessage()]));
            $result->setHeader('Content-Type', 'application/json');
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Unexpected error processing item request: ' . $e->getMessage());

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(500);
            $result->setContents(json_encode(['error' => 'Internal server error']));
            $result->setHeader('Content-Type', 'application/json');
            return $result;
        }
    }

    /**
     * Save item request to database
     *
     * @param string $token
     * @param string $dealerCode
     * @param string $partnerIdentity
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    private function saveItemRequest(
        string $token,
        string $dealerCode,
        string $partnerIdentity,
        string $itemId,
        int $quantity
    ): void {
        try {
            // Create new item record
            $itemModel = $this->itemFactory->create();
            $itemModel->setData([
                'token' => $token,
                'dealer_code' => $dealerCode,
                'partner_identity' => $partnerIdentity,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'status' => 'pending'
            ]);
            $itemModel->save();

            $this->logger->info(
                'Punchout: Item request saved',
                [
                    'token' => $token,
                    'itemId' => $itemId,
                    'dealerCode' => $dealerCode
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error saving item request: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get redirect URL from partner settings
     *
     * @param string $partnerIdentity
     * @return string|null
     */
    private function getRedirectUrl(string $partnerIdentity): ?string
    {
        $punchoutPartners = $this->getPunchoutPartnersManagement->getResult();

        foreach ($punchoutPartners as $partner) {
            if (strtolower($partner['identity'] ?? '') === strtolower($partnerIdentity)) {
                return $partner['punchoutRedirectUrl'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get corpAddressId from partner settings
     *
     * @param string $identity
     * @return string|null
     */
    private function getCorpAddressId(string $identity): ?string
    {
        $identity = strtolower($identity);
        $result = $this->getPunchoutPartnersManagement->getResult();

        foreach ($result as $item) {
            $itemIdentity = strtolower($item['identity'] ?? '');
            if ($itemIdentity === $identity) {
                return $item['corpAddressId'] ?? null;
            }
        }

        return null;
    }
}
