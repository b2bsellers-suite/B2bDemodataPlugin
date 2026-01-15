<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bSellersCore\Components\Employee\EmployeeEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomerSeeder
{
    private SalesChannelContext $salesChannelContext;
    private Context $context;
    private RegisterRoute $registerRoute;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
    ) {
        $this->context             = Context::createDefaultContext();
        $this->registerRoute       = $container->get(RegisterRoute::class);
        $this->salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->getSalesChannelDomain()->getSalesChannelId());
    }

    /**
     * @throws \Exception
     */
    public function run(OutputInterface $output): void
    {
        $output->writeln('Creating customers and employees...');

        $resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
        $dir         = new \DirectoryIterator($resourceDir . '/testdata/Customers/');

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                try {
                    $customerJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
                    $this->createCustomer($customerJson, $output);
                } catch (ConstraintViolationException $e) {
                    throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getViolations());
                } catch (\Exception $e) {
                    throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
                }
            }
        }
    }

    protected function getAddress($addressJson): RequestDataBag
    {
        return new RequestDataBag([
            'countryId'    => $this->getCountry($addressJson['country'])->getId(),
            'street'       => $addressJson['street'],
            'zipcode'      => $addressJson['zipcode'],
            'city'         => $addressJson['city'],
            'firstName'    => $addressJson['firstName'] ?? '',
            'lastName'     => $addressJson['lastName'] ?? '',
            'company'      => $addressJson['company'] ?? '',
            'salutationId' => $this->getSalutation($addressJson['salutation']) ? $this->getSalutation($addressJson['salutation'])->getId() : null,
        ]);
    }

    /**
     * @throws \Exception
     */
    private function createCustomer(array $customerJson, OutputInterface $output): void
    {
        $dataBag = $this->createCustomerDataBag($customerJson);

        if ($this->customerExists($customerJson['email'])) {
            $output->write(sprintf('Customer already exists: %s - Start updating...', $customerJson['email']));

            $customer = $this->getCustomerByEmail($customerJson['email']);
            $customer = $this->updateCustomer($customer, $dataBag);
        } else {
            $output->write(sprintf('Creating customer: %s', $customerJson['email']));

            $customerResponse = $this->registerRoute->register($dataBag, $this->salesChannelContext);
            $customer         = $customerResponse->getCustomer();
        }

        if (!$customer) {
            throw new \Exception('Customer not created');
        }

        $this->updateCustomerCustomFields($customer->getId(), $dataBag->all()['customFields']);

        $output->writeln('✅');

        $this->createCustomerEmployees($customer, $customerJson, $output);
        $this->createSalesRepRelations($customer, $customerJson);
    }

    private function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('sales_channel_domain.repository');

        return $repository->search(new Criteria(), $this->context)->first();
    }

    private function getSalutation($salutationKey = 'mr'): ?SalutationEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('salutation.repository');

        return $repository->search((new Criteria())->addFilter(new EqualsFilter('salutationKey', $salutationKey)), $this->context)->first();
    }

    private function getCountry($iso = 'DE'): CountryEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('country.repository');
        $country    = $repository->search((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)->first();

        if (!$country) {
            throw new \Exception('no country with iso ' . $iso . ' found');
        }

        return $country;
    }

    private function getLanguage($name = 'Deutsch'): LanguageEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('language.repository');

        return $repository->search((new Criteria())->addFilter(new EqualsFilter('name', $name)), $this->context)->first();
    }

    private function getCustomerByEmail($email): ?CustomerEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('customer.repository');

        return $repository->search((new Criteria())->addFilter(new EqualsFilter('email', $email)), $this->context)->first();
    }

    private function getCustomerByCustomerNumber(string $customerNumber): ?CustomerEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('customer.repository');

        return $repository->search((new Criteria())->addFilter(new EqualsFilter('customerNumber', $customerNumber)), $this->context)->first();
    }

    private function createEmployee($employee): void
    {
        /** @var EntityRepository $repository */
        $employeeRepository = $this->container->get('b2bsellers_employee.repository');
        $employeeRepository->upsert([$employee], $this->context);
    }

    private function createEmployee2Customer($employee2Customer): void
    {
        if (empty($employee2Customer['customerId']) || empty($employee2Customer['employeeId'])) {
            dd($employee2Customer);
        }
        /** @var EntityRepository $repository */
        $employee2CustomerRepository = $this->container->get('b2bsellers_employee_customer.repository');
        $employee2CustomerRepository->upsert([$employee2Customer], $this->context);
    }

    private function updateCustomerCustomFields($id, $customFields): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('customer.repository');

        $repository->update([[
            'id'           => $id,
            'customFields' => $customFields,
        ]], $this->context);
    }

    private function addSalesRepCustomer($salesRepId, $customerId): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->container->get('b2bsellers_sales_representative_customer.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesRepId', $salesRepId));
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $assignedCustomers = $repository->search($criteria, $this->context)->first();

        if (!empty($assignedCustomers)) {
            return;
        }

        $data = [
            'salesRepId' => $salesRepId,
            'customerId' => $customerId,
        ];

        $repository->create([$data], $this->context);
    }

    private function customerExists(string $email): bool
    {
        $customer = $this->getCustomerByEmail($email);

        return !empty($customer);
    }

    private function createCustomerDataBag($customerJson): RequestDataBag
    {
        $data = new RequestDataBag();

        $data->set('guest', (bool) $customerJson['guest']);
        $data->set('title', array_key_exists('title', $customerJson) ? (string) $customerJson['title'] : '');
        $data->set('company', array_key_exists('company', $customerJson) ? (string) $customerJson['company'] : '');
        $data->set('customerNumber', array_key_exists('customerNumber', $customerJson) ? (string) $customerJson['customerNumber'] : '');
        $data->set('salutationId', $this->getSalutation($customerJson['salutation']) ? $this->getSalutation($customerJson['salutation'])->getId() : null);
        $data->set('firstName', $customerJson['firstName'] ?? null);
        $data->set('lastName', $customerJson['lastName'] ?? null);
        $data->set('email', $customerJson['email']);
        $data->set('password', $customerJson['password']);
        $data->set('storefrontUrl', $this->salesChannelContext->getSalesChannel()->getDomains()->first()->getUrl());
        $data->set('accountType',
            $customerJson['accountType'] == CustomerEntity::ACCOUNT_TYPE_BUSINESS ?
                CustomerEntity::ACCOUNT_TYPE_BUSINESS : CustomerEntity::ACCOUNT_TYPE_PRIVATE
        );

        $data->set('billingAddress', $this->getAddress($customerJson['billingAddress']));
        $data->set('shippingAddress', $this->getAddress($customerJson['shippingAddress']));

        $customerJson['customFields']['b2b_url_login_authentication_hash'] = Uuid::randomHex();
        $data->set('customFields', new RequestDataBag($customerJson['customFields']));

        return $data;
    }

    /**
     * @throws \Exception
     */
    private function createCustomerEmployees(CustomerEntity $customer, array $customerJson, OutputInterface $output): void
    {
        if (!array_key_exists('customerEmployees', $customerJson) or !is_array($customerJson['customerEmployees'])) {
            return;
        }
        foreach ($customerJson['customerEmployees'] as $customerEmployee) {
            $employee = $this->prepareEmployee($customerEmployee);
            $this->createEmployee($employee);

            $output->writeln(sprintf(' - Employee created: %s %s ✅', $customerEmployee['firstName'], $customerEmployee['lastName']));

            $customerEmployee = $this->prepareEmployee2Customer($customerEmployee, $customer->getId());
            $this->createEmployee2Customer($customerEmployee);

            $output->writeln(sprintf(' - Employee mapped to customer: %s %s ✅', $employee['firstName'], $employee['lastName']));
        }
    }

    private function createSalesRepRelations(CustomerEntity $customer, $customerJson): void
    {
        if (isset($customerJson['isSalesRepOf']) && is_array($customerJson['isSalesRepOf'])) {
            foreach ($customerJson['isSalesRepOf'] as $salesRepRelation) {
                $relatedCustomer = $this->getCustomerByCustomerNumber($salesRepRelation['customerNumber']);

                if ($relatedCustomer) {
                    $this->addSalesRepCustomer($customer->getId(), $relatedCustomer->getId());
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function prepareEmployee2Customer(array $customerEmployee, string $customerId): array
    {
        $employee = $this->getEmployeeByEmail($customerEmployee['email']);

        $id = Uuid::randomHex();

        if ($employee) {
            /** @var EntityRepository $repository */
            $employee2CustomerRepository = $this->container->get('b2bsellers_employee_customer.repository');
            $result                      = $employee2CustomerRepository->search((new Criteria())->addFilter(
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('employeeId', $employee->getId())
            ), $this->context)->first();

            if ($result) {
                $id = $result->getId();
            }
        } else {
            throw new \Exception('Employee with email ' . $customerEmployee['email'] . ' not found! Cant create employee2customer relation!');
        }

        return [
            'id'           => $id,
            'customerId'   => $customerId,
            'employeeId'   => $employee->getId(),
            'admin'        => $customerEmployee['admin'] ?? false,
            'active'       => $customerEmployee['active'] ?? true,
            'roleId'       => $this->getRoleId($customerEmployee['role'] ?? null) ?? null,
            'customFields' => [
                'b2b_show_bonus' => $customerEmployee['showBonus'] ?? false,
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    private function getRoleId(?string $name)
    {
        if (empty($name) || $name == null || !is_string($name)) {
            return null;
        }
        /** @var EntityRepository $repository */
        $repository = $this->container->get('b2bsellers_employee_role.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('translated');
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('customerId', null),
            new EqualsFilter('name', $name),
        ]));

        $result = $repository->search($criteria, $this->context)->first();

        if (!$result) {
            throw new \Exception('Role with name ' . $name . ' not found!');
        }

        return $result->getId();
    }

    private function prepareEmployee(array $customerEmployee): array
    {
        $employee = $this->getEmployeeByEmail($customerEmployee['email']);

        return [
            'id'                  => $employee ? $employee->getId() : Uuid::randomHex(),
            'firstName'           => $customerEmployee['firstName'],
            'lastName'            => $customerEmployee['lastName'],
            'email'               => $customerEmployee['email'],
            'password'            => $customerEmployee['password'],
            'languageId'          => $this->getLanguage()->getId() ?? null,
            'title'               => $customerEmployee['title'] ?? null,
            'department'          => $customerEmployee['department'] ?? null,
            'phoneNumber'         => $customerEmployee['phoneNumber'] ?? null,
            'loginTarget'         => $customerEmployee['loginTarget'] ?? null,
            'trackActivity'       => $customerEmployee['trackActivity'] ?? true,
            'salutationId'        => ($this->getSalutation($customerEmployee['salutation']) ? $this->getSalutation($customerEmployee['salutation'])->getId() : null),
            'boundSalesChannelId' => $customerEmployee['boundSalesChannelId'] ?? null,
            'customFields'        => [
                'b2b_url_login_authentication_hash' => Uuid::randomHex(),
            ],
        ];
    }

    private function getEmployeeByEmail(string $email): ?EmployeeEntity
    {
        /** @var EntityRepository $repository */
        $employeeRepository = $this->container->get('b2bsellers_employee.repository');

        return $employeeRepository->search((new Criteria())->addFilter(new EqualsFilter('email', $email)), $this->context)->first();
    }

    private function updateCustomer(?CustomerEntity $customer, RequestDataBag $dataBag): ?CustomerEntity
    {
        $dataBag->set('id', $customer->getId());

        /** @var EntityRepository $repository */
        $customerRepository = $this->container->get('customer.repository');
        $customerRepository->update([$dataBag->all()], $this->context);

        return $this->getCustomerByEmail($customer->getEmail());
    }
}
