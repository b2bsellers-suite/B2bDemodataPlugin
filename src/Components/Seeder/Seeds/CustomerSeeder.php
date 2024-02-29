<?php

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bSellersCore\Components\Employee\Aggregate\EmployeeCustomer\EmployeeCustomerCollection;
use B2bSellersCore\Components\Employee\EmployeeEntity;
use DirectoryIterator;
use PHPUnit\Util\Exception;
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
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomerSeeder
{
	private SalesChannelContext $salesChannelContext;
	private ContainerInterface $container;
	private AbstractSalesChannelContextFactory $contextFactory;
	private Context $context;
	private RegisterRoute $registerRoute;

	public function __construct(ContainerInterface $container, AbstractSalesChannelContextFactory $contextFactory)
	{
		$this->contextFactory = $contextFactory;
		$this->container = $container;
		$this->context = Context::createDefaultContext();
		$this->registerRoute = $container->get(RegisterRoute::class);
		$this->salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->getSalesChannelDomain()->getSalesChannelId());
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function run()
	{
		echo "\n\nCreating customers and employees...\n";
		$resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
		$dir = new DirectoryIterator($resourceDir . '/testdata/Customers/');

		foreach ($dir as $fileInfo) {
			if (!$fileInfo->isDot()) {
				try {
					$customerJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
					$this->createCustomer($customerJson);
				} catch (ConstraintViolationException $e) {
					throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getViolations());
				} catch (\Exception $e) {
					throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
				}
			}
		}

	}

    /**
     * @throws \Exception
     */
    private function createCustomer($customerJson): void
	{
		$dataBag = $this->createCustomerDataBag($customerJson);

		if ($this->customerExists($customerJson['email'])) {
			echo "Customer already exists: " . $customerJson['email'] . " - Start updating...";
			$customer = $this->getCustomerByEmail($customerJson['email']);
			$customer = $this->updateCustomer($customer, $dataBag);
		} else {
			echo "Creating customer: " . $customerJson['email'] . " ";
			$customerResponse = $this->registerRoute->register($dataBag, $this->salesChannelContext);
			$customer = $customerResponse->getCustomer();
		}
		if(!$customer){
			throw new \Exception('Customer not created');
		}

		$name = $customerJson['firstName'] . ' ' . $customerJson['lastName'];
		if (isset($customerJson['company']) && $customerJson['company'] != '') {
			$name = $customerJson['company'];
		}

		$this->updateCustomerCustomFields($customer->getId(), $dataBag->all()['customFields']);
		echo "✅ \n";

		$this->createCustomerEmployees($customer, $customerJson);
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
		$country = $repository->search((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)->first();
		if (!$country) {
			throw new Exception('no country with iso ' . $iso . ' found');
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

	private function createEmployee($employee)
	{
		/** @var EntityRepository $repository */
		$employeeRepository = $this->container->get('b2bsellers_employee.repository');
		$employeeRepository->upsert([$employee], $this->context);

	}

	private function createEmployee2Customer($employee2Customer)
	{
		if (empty($employee2Customer['customerId']) || empty($employee2Customer['employeeId'])) {
			dd($employee2Customer);
		}
		/** @var EntityRepository $repository */
		$employee2CustomerRepository = $this->container->get('b2bsellers_employee_customer.repository');
		$employee2CustomerRepository->upsert([$employee2Customer], $this->context);

	}

	private function updateCustomerCustomFields($id, $customFields)
	{
		/** @var EntityRepository $repository */
		$repository = $this->container->get('customer.repository');

		$repository->update([[
			'id' => $id,
			'customFields' => $customFields
		]], $this->context);

	}

	private function addCustomerEmployee($customerId, $email, $admin = false, $roleId = null, $customFields = null)
	{
		/** @var EntityRepository $repository */
		$repository = $this->container->get('b2bsellers_employee.repository');


		$criteria = new Criteria();
		$criteria->addFilter(new EqualsFilter('email', $email));
		$criteria->addAssociation('customers');

		/** @var EmployeeEntity $customerEmployee */
		$customerEmployee = $repository->search($criteria, $this->context)->first();

		if (empty($customerEmployee)) {
			return;
		}

		/** @var EmployeeCustomerCollection $customers */
		$collection = $customerEmployee->getCustomers();

		if ($collection->filterByProperty('customerId', $customerId)->count() > 0) {
			return;
		}

		/** @var EntityRepository $repository */
		$assignedCustomerEmployeesRepository = $this->container->get('b2bsellers_employee_customer.repository');

		$data = [
			'employeeId' => $customerEmployee->getId(),
			'customerId' => $customerId,
			'active' => true,
			'admin' => $admin,
			'roleId' => $admin,
			'customFields' => $customFields
		];

		$assignedCustomerEmployeesRepository->create([$data], $this->context);
	}

	private function addSalesRepCustomer($salesRepId, $customerId)
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
			'customerId' => $customerId
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

		$data->set('guest', (bool)$customerJson['guest']);
		$data->set('title', array_key_exists('title', $customerJson) ? (string)$customerJson['title'] : '');
		$data->set('company', array_key_exists('company', $customerJson) ? (string)$customerJson['company'] : '');
		$data->set('customerNumber', array_key_exists('customerNumber', $customerJson) ? (string)$customerJson['customerNumber'] : '');
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

	protected function getAddress($addressJson): RequestDataBag
	{
		return new RequestDataBag([
			'countryId' => $this->getCountry($addressJson['country'])->getId(),
			'street' => $addressJson['street'],
			'zipcode' => $addressJson['zipcode'],
			'city' => $addressJson['city'],
			'firstName' => $addressJson['firstName'] ?? '',
			'lastName' => $addressJson['lastName'] ?? '',
			'company' => $addressJson['company'] ?? '',
			'salutationId' => $this->getSalutation($addressJson['salutation']) ? $this->getSalutation($addressJson['salutation'])->getId() : null,
		]);
	}

    /**
     * @throws \Exception
     */
    private function createCustomerEmployees(CustomerEntity $customer, $customerJson): void
	{
		if (!array_key_exists('customerEmployees', $customerJson) or !is_array($customerJson['customerEmployees'])) {
			return;
		}
		foreach ($customerJson['customerEmployees'] as $customerEmployee) {

			$employee = $this->prepareEmployee($customerEmployee);
			$this->createEmployee($employee);
			echo " - Employee created: " . $customerEmployee['firstName'] . " " . $customerEmployee['lastName'] . " ✅ \n";

			$customerEmployee = $this->prepareEmployee2Customer($customerEmployee, $customer->getId());
			$this->createEmployee2Customer($customerEmployee);
			echo " - Employee mapped to customer: " . $employee['firstName'] . " " . $employee['lastName'] . " ✅ \n";

		}
	}
	private function createSalesRepRelations(CustomerEntity $customer, $customerJson)
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
		$employee= $this->getEmployeeByEmail($customerEmployee['email']);

		$id = Uuid::randomHex();
		if ($employee){
			/** @var EntityRepository $repository */
			$employee2CustomerRepository = $this->container->get('b2bsellers_employee_customer.repository');
			$result = $employee2CustomerRepository->search((new Criteria())->addFilter(
				new EqualsFilter('customerId', $customerId),
				new EqualsFilter('employeeId', $employee->getId())
			), $this->context)->first();
			if ($result){
				$id = $result->getId();
			}
		} else {
			throw new \Exception('Employee with email ' . $customerEmployee['email'] . ' not found! Cant create employee2customer relation!');
		}

		$customerEmployee = [
			'id' => $id,
			'customerId' => $customerId,
			'employeeId' => $employee->getId(),
			'admin' => $customerEmployee['admin'] ?? false,
			'active' => $customerEmployee['active'] ?? true,
			'roleId' => $this->getRoleId($customerEmployee['role'] ?? null) ?? null,
			'customFields' => [
				'b2b_show_bonus' => $customerEmployee['showBonus'] ?? false
			]
		];

		return $customerEmployee;
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
			new EqualsFilter('customerId', NULL),
			new EqualsFilter('name', $name)
		]));

		$result = $repository->search($criteria, $this->context)->first();
		if(!$result) {
			throw new \Exception('Role with name ' . $name . ' not found!');
		}
		return $result->getId();

	}

	private function prepareEmployee(array $customerEmployee): array
    {
		$employee = $this->getEmployeeByEmail($customerEmployee['email']);

		$employee = [
			'id' => $employee ? $employee->getId() : Uuid::randomHex(),
			'firstName' => $customerEmployee['firstName'],
			'lastName' => $customerEmployee['lastName'],
			'email' => $customerEmployee['email'],
			'password' => $customerEmployee['password'],
			'languageId' => $this->getLanguage()->getId() ?? null,
			'title' => $customerEmployee['title'] ?? null,
			'department' => $customerEmployee['department'] ?? null,
			'phoneNumber' => $customerEmployee['phoneNumber'] ?? null,
			'loginTarget' => $customerEmployee['loginTarget'] ?? null,
			'trackActivity' => $customerEmployee['trackActivity'] ?? true,
			'salutationId' => ($this->getSalutation($customerEmployee['salutation']) ? $this->getSalutation($customerEmployee['salutation'])->getId() : null),
			'boundSalesChannelId' => $customerEmployee['boundSalesChannelId'] ?? null,
			'customFields' => [
				'b2b_url_login_authentication_hash' => Uuid::randomHex()
			]
		];
		return $employee;
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
