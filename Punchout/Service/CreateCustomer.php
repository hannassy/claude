<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tirehub\ApiMiddleware\Api\Request\LookupCommonDealersInterface;
use Tirehub\ApiMiddleware\Api\Request\LookupDealersInterface;
use Tirehub\ApiMiddleware\Api\Request\AssertB2bContactInterface;
use Tirehub\Punchout\Api\CreateCustomerInterface;
use Tirehub\Punchout\Model\Config;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Company\Api\CompanyManagementInterface;

class CreateCustomer implements CreateCustomerInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LookupCommonDealersInterface $lookupCommonDealers,
        private readonly LookupDealersInterface $lookupDealers,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly RegionFactory $regionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly AssertB2bContactInterface $assertB2bContact,
        private readonly LoggerInterface $logger,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CompanyManagementInterface $companyManagement
    ) {
    }

    public function execute(array $extrinsics, string $dealerCode): int
    {
        try {
            if (empty($dealerCode)) {
                throw new LocalizedException(__('Corporate address ID is required'));
            }

            $this->logger->info("Punchout: Creating customer for dealerCode: {$dealerCode}");

            // Get dealer information directly using $dealerCode
            $dealerInfo = $this->getDealerInfo($dealerCode);

            // Generate email based on $dealerCode
            $email = $this->generateEmail($dealerCode);

            // Check if customer already exists
            try {
                $existingCustomer = $this->customerRepository->get($email);
                $this->logger->info("Punchout: Using existing customer with email {$email}");
                return (int)$existingCustomer->getId();
            } catch (NoSuchEntityException $e) {
                // Create new customer
                $customer = $this->createCustomer($extrinsics, $dealerInfo, $dealerCode);

                // Reload customer to ensure we have the latest data
                $customer = $this->customerRepository->getById($customer->getId());

                // Sync customer with external system
                $this->syncCustomerToApi($customer, $dealerCode);

                $resellerId = $dealerInfo['customerId'] ?? null;

                // Get company information
                $company = $this->getCustomerCompany($resellerId);
                if (!$company) {
                    throw new LocalizedException(__('Cannot find company for reseller ID: %1', $dealerCode));
                }

                // Assign customer to company
                $this->companyManagement->assignCustomer(
                    $company->getId(),
                    $customer->getId()
                );

                // Create address for customer
                if ($customer->getId()) {
                    $this->createCustomerAddress($customer, $dealerCode, $company);
                }

                $this->logger->info("Punchout: Created and synced new customer with ID {$customer->getId()}");
                return (int)$customer->getId();
            }
        } catch (\Exception $e) {
            $this->logger->error("Punchout: Customer creation failed: {$e->getMessage()}");
            throw new LocalizedException(__('Unable to create customer: %1', $e->getMessage()));
        }
    }

    private function createCustomer(
        array $extrinsics,
        array $dealerInfo,
        string $dealerCode
    ): CustomerInterface {
        $websiteId = $this->storeManager->getWebsite()->getId();

        // Get customer details, preferring extrinsics but falling back to dealer info or defaults
        $firstName = $extrinsics['FirstName'] ?? ($dealerInfo['firstName'] ?? 'Punchout');
        $lastName = $extrinsics['LastName'] ?? ($dealerInfo['lastName'] ?? 'User');
        $phoneNumber = $dealerInfo['contactPhone'] ?? 'N/A';

        // Create customer object
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->setFirstname($firstName);
        $customer->setLastname($lastName);
        $customer->setEmail($this->generateEmail($dealerCode));

        // Set custom attributes
        $customer->setCustomAttribute('erp_user_role', '5644');
        $customer->setCustomAttribute('erp_store', $dealerCode);
        $customer->setCustomAttribute('view_billing', '20950');
        $customer->setCustomAttribute('retail_price_only', '0');
        $customer->setCustomAttribute('user_rights', '9197');
        $customer->setCustomAttribute('user_dropshipping', '1');
        $customer->setCustomAttribute('role', '35605');

        // Set mobile phone if available
        if (!empty($phoneNumber)) {
            $customer->setCustomAttribute('mobile_phone', $phoneNumber);
        }

        // Save and return customer
        return $this->customerRepository->save($customer);
    }

    /**
     * Get company for customer based on $dealerCode
     *
     * @param string $dealerCode
     * @return CompanyInterface|null
     */
    private function getCustomerCompany(string $dealerCode): ?CompanyInterface
    {
        $companies = $this->companyRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('reseller_id', $dealerCode)
                ->create()
        )->getItems();

        if (empty($companies)) {
            $this->logger->warning("Punchout: No company found for reseller ID {$dealerCode}");
            return null;
        }

        return current($companies);
    }

    /**
     * Get dealer information from API
     *
     * @param string $dealerCode
     * @return array
     */
    private function getDealerInfo(string $dealerCode): array
    {
        try {
            $result = $this->lookupDealers->execute(['dealerCode' => $dealerCode]);
            $dealerInfo = $result['results'][0] ?? [];

            if (empty($dealerInfo)) {
                $this->logger->warning("Punchout: No dealer info found for dealerCode {$dealerCode}");
                return [];
            }

            return $dealerInfo;
        } catch (\Exception $e) {
            $this->logger->error("Punchout: Error getting dealer info: {$e->getMessage()}");
            return [];
        }
    }

    private function createCustomerAddress(
        CustomerInterface $customer,
        string $dealerCode,
        CompanyInterface $company
    ): ?AddressInterface {
        try {
            $dealerInfo = $this->getDealerInfo($dealerCode);

            if (empty($dealerInfo) || empty($dealerInfo['shipToLocation'])) {
                $this->logger->warning("Punchout: No address data found for dealerCode {$dealerCode}");
                return null;
            }

            $address = $this->addressFactory->create();
            $address->setCustomerId($customer->getId());
            $address->setFirstname($customer->getFirstname());
            $address->setLastname($customer->getLastname());
            $address->setIsDefaultBilling(true);
            $address->setIsDefaultShipping(true);
            $address->setCompany($company->getCompanyName());

            $this->populateAddressData($address, $dealerInfo['shipToLocation']);

            $savedAddress = $this->addressRepository->save($address);
            $this->logger->info("Punchout: Created address for customer ID {$customer->getId()}");

            return $savedAddress;
        } catch (\Exception $e) {
            $this->logger->error("Punchout: Failed to create address: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Sync customer to external API
     *
     * @param CustomerInterface $customer
     * @return void
     * @throws LocalizedException
     */
    private function syncCustomerToApi(CustomerInterface $customer, string $dealerCode): void
    {
        try {
            // Reload customer to get updated data including the address
            $customer = $this->customerRepository->getById($customer->getId());
            $params = $this->prepareCustomerSyncParams($customer, $dealerCode);

            // Call external API to sync customer
            $this->logger->info('Punchout: Syncing customer with params: ' . json_encode($params));
            $result = $this->assertB2bContact->execute($params);

            // Process result and update customer with ERP contact code
            $erpContactCode = $result['id'] ?? null;

            if (!$erpContactCode) {
                throw new LocalizedException(
                    __('Failed to sync customer')
                );
            }

            // Update customer with ERP contact code
            $customer->setCustomAttribute('erp_contact_code', $erpContactCode);
            $this->customerRepository->save($customer);

            $this->logger->info("Punchout: Successfully synced customer ID {$customer->getId()} with ERP contact code {$erpContactCode}");
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical("Punchout: Error syncing customer to api: {$e->getMessage()}");
            throw new LocalizedException(
                __('Failed to sync customer with api: %1', $e->getMessage())
            );
        }
    }

    /**
     * Prepare parameters for customer sync
     *
     * @param CustomerInterface $customer
     * @return array
     */
    private function prepareCustomerSyncParams(CustomerInterface $customer, string $dealerCode): array
    {
        $params = [
            'id' => '0',
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'emailAddress' => $customer->getEmail(),
            'phone' => '',
            'addressId' => $dealerCode
        ];

        // Get phone number
        $mobilePhone = $customer->getCustomAttribute('mobile_phone');
        if ($mobilePhone) {
            $params['phone'] = $mobilePhone->getValue();
        }

        return $params;
    }

    /**
     * Get ERP address code from address
     *
     * @param AddressInterface $address
     * @return string|null
     */
    private function getErpAddressCode(AddressInterface $address): ?string
    {
        $erpAddressAttribute = $address->getCustomAttribute('erp_erp_address_code');
        if (!$erpAddressAttribute) {
            return null;
        }

        $erpAddressCode = $erpAddressAttribute->getValue();

        // Remove 'D' prefix if present
        if ($erpAddressCode && str_contains($erpAddressCode, 'D')) {
            $erpAddressCode = str_replace('D', '', $erpAddressCode);
        }

        return $erpAddressCode;
    }

    /**
     * Generate email from dealer code
     *
     * @param string $dealerCode
     * @return string
     */
    private function generateEmail(string $dealerCode): string
    {
        $emailTemplate = $this->config->getCustomerEmailTemplate();
        return str_replace('shiptoID', $dealerCode, $emailTemplate);
    }

    /**
     * Populate address data
     *
     * @param AddressInterface $address
     * @param array $erpAddress
     * @return AddressInterface
     */
    private function populateAddressData(AddressInterface $address, array $erpAddress): AddressInterface
    {
        if (empty($erpAddress) || empty($erpAddress['address'])) {
            return $address;
        }

        $_address = $erpAddress['address'];

        // Build street lines
        $street = [];
        for ($i = 1; $i <= 20; $i++) {
            if (isset($_address['address' . $i]) && !empty($_address['address' . $i])) {
                $street[] = $_address['address' . $i];
            }
        }

        if (!empty($street)) {
            $address->setStreet($street);
        }

        // Set basic address fields
        if (!empty($_address['city'])) {
            $address->setCity($_address['city']);
        }

        if (!empty($_address['postalCode'])) {
            $address->setPostcode($_address['postalCode']);
        }

        if (!empty($_address['country'])) {
            $address->setCountryId($_address['country']);
        }

        $address->setTelephone(!empty($_address['phoneNumber']) ? $_address['phoneNumber'] : 'N/A');

        if (!empty($_address['locationName'])) {
            $address->setCompany($_address['locationName']);
        }

        // Set region/state
        if (!empty($_address['state']) && !empty($_address['country'])) {
            $regionCode = $_address['state'];
            $countryCode = $_address['country'];

            $region = $this->regionFactory->create()->loadByCode($regionCode, $countryCode);
            if ($region && !$region->isObjectNew()) {
                $address->setRegionId($region->getId());
            }
        }

        // Set custom attributes if needed
        if (!empty($erpAddress['locationId'])) {
            $address->setCustomAttribute('erp_erp_address_code', $erpAddress['locationId']);
        }

        return $address;
    }
}