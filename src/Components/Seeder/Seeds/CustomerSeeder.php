<?php

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bSellersCore\Components\Employee\Aggregate\EmployeeCustomer\EmployeeCustomerCollection;
use B2bSellersCore\Components\Employee\EmployeeEntity;
use DirectoryIterator;
use PHPUnit\Util\Exception;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Style\SymfonyStyle;
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

	private function createCustomer($customerJson): void
	{
		if ($this->customerExists($customerJson['email'])) {
			return;
		}
		$dataBag = $this->createCustomerDataBag($customerJson);
		$customerResponse = $this->registerRoute->register($dataBag, $this->salesChannelContext);


		$name = $customerJson['firstName'] . ' ' . $customerJson['lastName'];
		if (isset($customerJson['company']) && $customerJson['company'] != '') {
			$name = $customerJson['company'];
		}
		echo "Customer created: " . $name . "\n";

		$this->updateCustomerCustomFields($customerResponse->getCustomer()->getId(), $dataBag->all()['customFields']);
		$customer = $customerResponse->getCustomer();

		$this->createCustomerEmployees($customer, $customerJson);
		$this->createSalesRepRelations($customer, $customerJson);
	}

	private function getSalesChannelDomain(): SalesChannelDomainEntity
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('sales_channel_domain.repository');

		return $repository->search(new Criteria(), $this->context)->first();
	}

	private function getSalutation($salutationKey = 'mr'): ?SalutationEntity
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('salutation.repository');

		return $repository->search((new Criteria())->addFilter(new EqualsFilter('salutationKey', $salutationKey)), $this->context)->first();
	}

	private function getCountry($iso = 'DE'): CountryEntity
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('country.repository');
		$country = $repository->search((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)->first();
		if (!$country) {
			throw new Exception('no country with iso ' . $iso . ' found');
		}
		return $country;
	}

	private function getLanguage($name = 'Deutsch'): LanguageEntity
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('language.repository');

		return $repository->search((new Criteria())->addFilter(new EqualsFilter('name', $name)), $this->context)->first();
	}

	private function getCustomerByEmail($email): ?CustomerEntity
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('customer.repository');

		return $repository->search((new Criteria())->addFilter(new EqualsFilter('email', $email)), $this->context)->first();
	}

	private function getCustomerByCustomerNumber(string $customerNumber): ?CustomerEntity
	{

		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('customer.repository');

		return $repository->search((new Criteria())->addFilter(new EqualsFilter('customerNumber', $customerNumber)), $this->context)->first();
	}

	private function createCustomerEmployee($customerId, $data)
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('b2b_employee.repository');

		$result = $repository->search((new Criteria())->addFilter(new EqualsFilter('email', $data['email'])), $this->context)->first();

		if (!empty($result)) {
			$this->addCustomerEmployee($customerId, $data['email'], $data['admin'], $data['showBonus']);
			return;
		}

		$data['languageId'] = $this->getLanguage()->getId();
		$data['customFields'] = ['b2b_url_login_authentication_hash' => Uuid::randomHex()];
		$repository->create([$data], $this->context);

		$this->addCustomerEmployee($customerId, $data['email'], $data['admin'], $data['showBonus']);
	}

	private function updateCustomerCustomFields($id, $customFields)
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('customer.repository');

		$repository->update([[
			'id' => $id,
			'customFields' => $customFields
		]], $this->context);

	}

	private function addCustomerEmployee($customerId, $email, $admin = false, $showBonus = false)
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('b2b_employee.repository');


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

		/** @var EntityRepositoryInterface $repository */
		$assignedCustomerEmployeesRepository = $this->container->get('b2b_employee_customer.repository');

		$data = [
			'employeeId' => $customerEmployee->getId(),
			'customerId' => $customerId,
			'admin' => $admin,
			'customFields' => ['b2b_show_bonus' => $showBonus]
		];

		$assignedCustomerEmployeesRepository->create([$data], $this->context);
	}

	private function addSalesRepCustomer($salesRepId, $customerId)
	{
		/** @var EntityRepositoryInterface $repository */
		$repository = $this->container->get('b2b_sales_representative_customer.repository');

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
			$data->set('firstName', $customerJson['firstName']) ?? null;
			$data->set('lastName', $customerJson['lastName']) ?? null;
		$data->set('email', $customerJson['email']);
		$data->set('password', $customerJson['password']);
		$data->set('storefrontUrl', $this->salesChannelContext->getSalesChannel()->getDomains()->first()->getUrl());
		$data->set('accountType',
			$customerJson['accountType'] == CustomerEntity::ACCOUNT_TYPE_BUSINESS ?
				CustomerEntity::ACCOUNT_TYPE_BUSINESS : CustomerEntity::ACCOUNT_TYPE_PRIVATE
		);

		$data->set('billingAddress', $this->getAddress($customerJson['billingAddress']));
		$data->set('shippingAddress', $this->getAddress($customerJson['shippingAddress']));
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

	private function createCustomerEmployees(CustomerEntity $customer, $customerJson): void
	{
		if (!array_key_exists('customerEmployees', $customerJson) or !is_array($customerJson['customerEmployees'])) {
			return;
		}
		foreach ($customerJson['customerEmployees'] as $customerEmployee) {
			$customerEmployee['salutationId'] = $this->getSalutation($customerEmployee['salutation'])->getId();
			unset($customerEmployee['salutation']);

			$this->createCustomerEmployee($customer->getId(), $customerEmployee);
			echo " - Employee created/mapped: " . $customerEmployee['firstName'] . " " . $customerEmployee['lastName'] . " \n";
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


}
