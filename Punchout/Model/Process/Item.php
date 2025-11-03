<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Process;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Tirehub\Punchout\Model\ItemFactory;
use Tirehub\Punchout\Model\ResourceModel\Item as ItemResource;
use Tirehub\Punchout\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;
use Magento\Framework\Logger\Monolog;
use Tirehub\Punchout\Model\Config;
use Tirehub\Punchout\Model\LogFactory;
use Tirehub\ApiMiddleware\Api\Request\LookupDealersInterface;
use Exception;

class Item
{
    private const COOKIE_NAME = 'punchout_items_identifier';

    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ItemFactory $itemFactory,
        private readonly ItemResource $itemResource,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly LookupDealersInterface $lookupDealers,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly Monolog $logger,
        private readonly Config $config,
        private readonly LogFactory $logFactory
    ) {
    }

    public function execute(RequestInterface $request): ResultInterface
    {
        $log = $this->logFactory->create();

        try {
            $partnerIdentity = $request->getParam('partnerIdentity') ?? $request->getParam('PartnerIdentity');
            $dealerCode = $request->getParam('dealerCode') ?? $request->getParam('DealerCode');
            $itemId = $request->getParam('itemId') ?? $request->getParam('ItemId');
            $quantityNeeded = (int)($request->getParam('quantityNeeded') ?? (int)$request->getParam('QuantityNeeded'));

            if (empty($partnerIdentity) || empty($dealerCode)) {
                throw new LocalizedException(__('Partner Identity and Dealer Code are required parameters'));
            }

            $this->logger->info('Punchout: Processing item request', [
                'partnerIdentity' => $partnerIdentity,
                'dealerCode' => $dealerCode,
                'itemId' => $itemId,
                'quantityNeeded' => $quantityNeeded
            ]);

            $dealerCode = $this->getValidDealerCode($dealerCode, $partnerIdentity);
            if (!$dealerCode) {
                throw new LocalizedException(__('Invalid dealer code or partner identity'));
            }

            $identifier = $this->getOrCreateIdentifier();

            $this->saveItemToDatabase($identifier, $dealerCode, $partnerIdentity, $itemId, $quantityNeeded);

            $this->setCookie($identifier);

            $log->setData([
                'type' => 'item',
                'request' => json_encode($request->getParams()),
                'response' => 'Success',
                'partner_identity' => $partnerIdentity,
                'dealer_code' => $dealerCode,
            ]);
            $log->save();

            $redirectUrl = $this->getRedirectUrl($partnerIdentity);
            if (!$redirectUrl) {
                throw new LocalizedException(__('Redirect URL not configured for partner: %1', $partnerIdentity));
            }

            $this->logger->info('Punchout: Redirecting to customer URL', [
                'url' => $redirectUrl,
                'identifier' => $identifier
            ]);

            $redirect = $this->redirectFactory->create();
            $redirect->setUrl($redirectUrl);
            return $redirect;

        } catch (Exception $e) {
            $this->logger->error('Punchout: Item processing error: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            $log->setData([
                'type' => 'item',
                'request' => json_encode($request->getParams()),
                'response' => 'Error: ' . $e->getMessage(),
                'partner_identity' => $partnerIdentity ?? null,
                'dealer_code' => $dealerCode ?? null,
            ]);
            $log->save();

            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(500);
            $result->setContents('Error: ' . $e->getMessage());
            return $result;
        }
    }

    private function getOrCreateIdentifier(): string
    {
        $identifier = $this->cookieManager->getCookie(self::COOKIE_NAME);

        if (!$identifier) {
            $identifier = $this->generateIdentifier();
        }

        return $identifier;
    }

    private function generateIdentifier(): string
    {
        return sprintf(
            '%s-%s',
            bin2hex(random_bytes(16)),
            time()
        );
    }

    private function saveItemToDatabase(
        string $identifier,
        string $dealerCode,
        string $partnerIdentity,
        ?string $itemId,
        int $quantity
    ): void {
        if (empty($itemId)) {
            return;
        }

        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier)
            ->addFieldToFilter('item_id', $itemId);

        $existingItem = $collection->getFirstItem();

        if ($existingItem->getId()) {
            $existingItem->setQuantity($quantity);
            $existingItem->setUpdatedAt(date('Y-m-d H:i:s'));
            $this->itemResource->save($existingItem);

            $this->logger->info('Punchout: Updated existing item', [
                'identifier' => $identifier,
                'item_id' => $itemId,
                'quantity' => $quantity
            ]);
        } else {
            $item = $this->itemFactory->create();
            $item->setData([
                'token' => md5($identifier . $itemId . time()),
                'identifier' => $identifier,
                'dealer_code' => $dealerCode,
                'partner_identity' => $partnerIdentity,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'status' => 'pending'
            ]);
            $this->itemResource->save($item);

            $this->logger->info('Punchout: Created new item', [
                'identifier' => $identifier,
                'item_id' => $itemId,
                'quantity' => $quantity
            ]);
        }
    }

    private function setCookie(string $identifier): void
    {
        $lifetime = $this->config->getCookieLifetime();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration($lifetime)
            ->setPath('/')
            ->setHttpOnly(true)
            ->setSecure(true)
            ->setSameSite('Lax');

        $this->cookieManager->setPublicCookie(
            self::COOKIE_NAME,
            $identifier,
            $metadata
        );

        $this->logger->info('Punchout: Cookie set', [
            'identifier' => $identifier,
            'lifetime' => $lifetime
        ]);
    }

    private function getValidDealerCode(string $dealerCode): ?string
    {
        try {
            $result = $this->lookupDealers->execute(['dealerCode' => $dealerCode]);
            $exists =  $result['results'][0]['shipToLocation']['locationId'] ?? null;

            return $exists ? $dealerCode : null;
        } catch (Exception $e) {
            $this->logger->error('Punchout: Error in validateDealerExists on items of interests: ' . $e->getMessage());
            return null;
        }
    }

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
}
