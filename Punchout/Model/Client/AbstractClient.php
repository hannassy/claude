<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Client;

use Tirehub\Punchout\Api\Data\SessionInterface;
use Tirehub\Punchout\Model\Session;
use Tirehub\Punchout\Model\SessionFactory;
use Tirehub\Punchout\Model\ResourceModel\Session as SessionResource;
use Tirehub\Punchout\Service\GetPunchoutPartnersManagement;

abstract class AbstractClient
{
    public const CONTENT_TYPE_TEXT_XML = 'text/xml';

    protected ?string $identity = null;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly GetPunchoutPartnersManagement $getPunchoutPartnersManagement,
        private readonly SessionResource $sessionResource
    ) {
    }

    protected function saveSession(
        string $buyerCookie,
        string $identity,
        array $extrinsics,
        string $browserFormPostUrl,
        ?string $addressId
    ) {
        $session = $this->sessionFactory->create();
        $session->load($buyerCookie, SessionInterface::BUYER_COOKIE);
        $session->setData(SessionInterface::PARTNER_IDENTITY, $identity);

        if (!$session->getId()) {
            $session->setData(SessionInterface::BUYER_COOKIE, $buyerCookie);
            $session->setData(SessionInterface::CLIENT_TYPE, 'default');
            $session->setData(SessionInterface::STATUS, SessionInterface::STATUS_NEW);
        }

        $corpAddressId = $this->getCorpAddressId($identity);

        if ($corpAddressId) {
            $session->setData(SessionInterface::CORP_ADDRESS_ID, $corpAddressId);
        }

        // Set user data
        $email = $extrinsics['UserEmail'] ?? null;
        $fullName = $extrinsics['UserFullName'] ?? null;
        $firstName = $extrinsics['FirstName'] ?? null;
        $lastName = $extrinsics['LastName'] ?? null;
        $phone = $extrinsics['PhoneNumber'] ?? null;

        if ($email) {
            $session->setData(SessionInterface::EMAIL, $email);
        }

        if ($fullName) {
            $session->setData(SessionInterface::FULL_NAME, $fullName);
        }

        if ($firstName) {
            $session->setData(SessionInterface::FIRST_NAME, $firstName);
        }

        if ($lastName) {
            $session->setData(SessionInterface::LAST_NAME, $lastName);
        }

        if ($phone) {
            $session->setData(SessionInterface::PHONE, $phone);
        }

        // Set browser form post URL and ship to address
        if ($browserFormPostUrl) {
            $session->setData(SessionInterface::BROWSER_FORM_POST_URL, $browserFormPostUrl);
        }

        if ($addressId) {
            $session->setData(SessionInterface::ADDRESS_ID, $addressId);
        }

        $this->sessionResource->save($session);

        return $session;
    }

    protected function getCorpAddressId(string $identity): ?string
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
