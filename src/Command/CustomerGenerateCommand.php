<?php

namespace B2bDemodata\Command;

use Faker\Factory;
use Faker\Generator;
use Maltyxx\ImagesGenerator\ImagesGeneratorProvider;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'b2b:test-data:create:customer',
)]
class CustomerGenerateCommand extends Command
{
    public function __construct(
        private readonly AbstractRegisterRoute              $registerRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly EntityRepository                   $customerRepository,
        private readonly EntityRepository                   $employeeRepository,
        private readonly EntityRepository                   $employeeCustomerRepository,
        private readonly EntityRepository                   $salesRepresentativeCustomerRepository,
    )
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this->addArgument('storefrontUrl', InputArgument::REQUIRED, 'Storefront URL');
        // TODO: Get random country
        $this->addArgument('countryId', InputArgument::REQUIRED, 'Country UUID');
        // TODO: Get id from url
        $this->addArgument('salesChannelId', InputArgument::REQUIRED, 'Sales channel UUID');
        // TODO: Get random salutation
        $this->addArgument('salutationId', InputArgument::REQUIRED, 'Salutation UUID');
        // TODO: Get random sales rep
        $this->addArgument('salesRepId', InputArgument::REQUIRED, 'Sales Rep UUID');

        $this->addOption('customer-amount', null, InputOption::VALUE_OPTIONAL, 'How many customers should be generated', 500);
        $this->addOption('employee-amount', null, InputOption::VALUE_OPTIONAL, 'How many employees should be generated', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = $this->salesChannelContextFactory->create(Uuid::randomHex(), $input->getArgument('salesChannelId'));
        $customerIds = [];

        $io->writeln('Creating customers');

        $progress = $io->createProgressBar($input->getOption('customer-amount'));
        $progress->start();

        for ($i = 0; $i <= $input->getOption('customer-amount'); $i++) {
            try {
                $customerResponse = $this->registerRoute->register(
                    $this->createCustomerDataBag(
                        $input->getArgument('storefrontUrl'),
                        $input->getArgument('countryId'
                        )
                    ),
                    $context
                );

                $customerIds[] = $customerResponse->getCustomer()->getId();
            } catch (\Throwable $e) {
                $io->warning($e->getMessage());
            }

            if ($i % 10 === 0) {
                $progress->advance(10);
            }
        }

        $progress->finish();

        $io->writeln('Updating custom fields and setting sales rep');

        $this->updateCustomerCustomFields($customerIds, $context->getContext());
        $this->addSalesRepCustomer($input->getArgument('salesRepId'), $customerIds, $context->getContext());

        $io->writeln('Creating employees');

        $progress = $io->createProgressBar($input->getOption('employee-amount'));
        $progress->start();

        $employees = [];

        for ($i = 0; $i <= $input->getOption('employee-amount'); $i++) {
            $employees[] = $this->prepareEmployee($input->getArgument('salutationId'));
            $progress->advance();
        }

        $this->employeeRepository->upsert($employees, $context->getContext());
        $progress->finish();

        $io->writeln('Mapping employees to customers');

        $mapping = [];

        foreach ($customerIds as $customerId) {
            $mapping = array_merge(
                array_map(
                    function (string $employeeId) use ($customerId) {
                        return [
                            'id' => Uuid::randomHex(),
                            'customerId' => $customerId,
                            'employeeId' => $employeeId,
                            'admin' => true,
                            'active' => true,
                            'customFields' => [
                                'b2b_show_bonus' => false
                            ]
                        ];
                    },
                    array_map(
                        function (array $employee) {
                            return $employee['id'];
                        },
                        $employees
                    )
                ),
                $mapping
            );
        }

        $this->employeeCustomerRepository->upsert($mapping, $context->getContext());

        $io->success('Finished');

        return 0;
    }

    private function addSalesRepCustomer(string $salesRepId, array $customerIds, Context $context): void
    {
        $this->salesRepresentativeCustomerRepository->create(
            array_map(
                function (string $customerId) use ($salesRepId) {
                    return [
                        'salesRepId' => $salesRepId,
                        'customerId' => $customerId,
                    ];
                },
                $customerIds
            ),
            $context
        );
    }

    private function prepareEmployee(string $salutationId): array
    {
        $faker = $this->getFaker();
        return [
            'id' => Uuid::randomHex(),
            'firstName' => $faker->firstName(),
            'lastName' => $faker->lastName(),
            'email' => $faker->email(),
            'password' => 'b2bsellers',
            'salutationId' => $salutationId,
            'customFields' => [
                'b2b_url_login_authentication_hash' => Uuid::randomHex()
            ]
        ];
    }

    private function updateCustomerCustomFields(array $ids, Context $context): void
    {
        $this->customerRepository->update(
            array_map(
                function (string $id) {
                    return [
                        'id' => $id,
                        'customFields' => [
                            'b2b_platform_access' => true,
                            'b2b_sales_representative' => false,
                            'b2b_supervisor' => false
                        ],
                    ];
                },
                $ids
            ),
            $context
        );
    }

    private function createCustomerDataBag(string $storefrontUrl, string $countryId): RequestDataBag
    {
        $faker = $this->getFaker();
        $data = new RequestDataBag();

        $firstName = $faker->firstName();
        $lastName = $faker->lastName();
        $company = $faker->company();

        $data->set('guest', false);
        $data->set('company', $company);
        $data->set('firstName', $firstName);
        $data->set('lastName', $lastName);
        $data->set('email', $faker->email());
        $data->set('password', 'b2bsellers');
        $data->set('storefrontUrl', $storefrontUrl);
        $data->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);

        $data->set('billingAddress', $this->getAddress($countryId, $firstName, $lastName, $company));

        return $data;
    }

    private function getAddress(string $countryId, string $firstName, string $lastName, string $company): RequestDataBag
    {
        $faker = $this->getFaker();

        return new RequestDataBag([
            'countryId' => $countryId,
            'street' => $faker->streetAddress(),
            'zipcode' => $faker->postcode(),
            'city' => $faker->city(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'company' => $company,
        ]);
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        return $faker;
    }
}
