<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Block\Portal;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\RequestInterface;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\ApiMiddleware\Api\Request\LookupCommonDealersInterface;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Serialize\SerializerInterface;

class Address extends Template
{
    private ?string $locations = null;

    public function __construct(
        Context $context,
        private readonly RequestInterface $request,
        private readonly SessionFactory $sessionFactory,
        private readonly LookupCommonDealersInterface $lookupCommonDealers,
        private readonly Monolog $logger,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBuyerCookie(): string
    {
        return $this->serializer->serialize($this->request->getParam('cookie', ''));
    }

    public function getErrorMessage(): string
    {
        return $this->request->getParam('error', '');
    }

    public function getSession()
    {
        $buyerCookie = $this->request->getParam('cookie', '');
        if (empty($buyerCookie)) {
            return null;
        }

        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);

        if (!$session->getId()) {
            return null;
        }

        return $session;
    }

    public function getAddresses(): string
    {
        $session = $this->getSession();
        if (!$session) {
            $this->locations = '';
            return $this->locations;
        }

        try {
            // Try to get dealer code from session, if available
            $corpAddressId = $session->getData(SessionInterface::CORP_ADDRESS_ID);
            if ($corpAddressId) {
                $params['dealerCode'] = $corpAddressId;
            }

            // If we have no parameters, we can't filter the locations
            if (empty($params)) {
                $this->logger->warning('Punchout: No filtering parameters available for locations lookup');
                $this->locations = '';
                return $this->locations;
            }

            // Call API to get locations
            $result = $this->lookupCommonDealers->execute($params);
            $result = $result['results'] ?? [];

            if ($result) {
                $data = [];
                foreach ($result as $item) {
                    $data[] = [
                        'label' => $item['shipToLocation']['locationName'] ?? '',
                        'value' => $item['dealerCode'] ?? '',
                    ];
                }

                $this->locations = $this->serializer->serialize($data);

                // Log the count
                $count = count($result);
                $this->logger->info("Punchout: Found {$count} locations using params: " . json_encode($params));
            } else {
                $this->locations = '';
                $this->logger->error('Punchout: No locations found using params: ' . json_encode($params));
            }
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error loading locations: ' . $e->getMessage());
            $this->locations = '';
        }

        return $this->locations;
    }

    public function getPartnerName():string
    {
        $session = $this->getSession();

        return $this->serializer->serialize($session->getData(SessionInterface::PARTNER_IDENTITY));
    }
}
