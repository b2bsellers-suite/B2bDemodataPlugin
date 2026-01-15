<?php

declare(strict_types=1);

namespace B2bDemodata\Command;

use Faker\Factory;
use Faker\Generator;
use Maltyxx\ImagesGenerator\ImagesGeneratorProvider;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $employeeRepository,
        private readonly EntityRepository $employeeCustomerRepository,
        private readonly EntityRepository $salesRepresentativeCustomerRepository,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $salutationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('customer-amount', null, InputOption::VALUE_OPTIONAL, 'How many customers should be generated', 500);
        $this->addOption('employee-amount', null, InputOption::VALUE_OPTIONAL, 'How many employees should be generated', 50);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $defaultContext = Context::createDefaultContext();
        $domains        = $this->salesChannelDomainRepository->search(new Criteria(), $defaultContext)->getEntities();
        $salesReps      = $this->customerRepository->search(
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('customFields.b2b_sales_representative', true)
                ),
            $defaultContext
        )->getEntities();
        $countries   = $this->countryRepository->searchIds(new Criteria(), $defaultContext)->getIds();
        $salutations = $this->salutationRepository->searchIds(new Criteria(), $defaultContext)->getIds();

        $storefrontUrl  = $this->getStorefrontUrl($io, $domains);
        $salesChannelId = $this->getSalesChannelId($storefrontUrl, $domains);
        $salesRep       = $this->getSalesRep($io, $salesReps);
        $salesRepId     = $this->getSalesRepId($salesRep, $salesReps);
        $context        = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $io->writeln('Creating customers');
        $progress    = $io->createProgressBar($input->getOption('customer-amount'));
        $customerIds = $this->createCustomers(
            $input->getOption('customer-amount'),
            $storefrontUrl,
            $countries,
            $progress,
            $context
        );

        $io->writeln('Updating custom fields and setting sales rep');
        $this->updateCustomerCustomFields(
            $customerIds,
            $context->getContext()
        );
        $this->addSalesRepCustomer(
            $salesRepId,
            $customerIds,
            $context->getContext()
        );

        $io->writeln('Creating employees');

        $progress  = $io->createProgressBar($input->getOption('employee-amount'));
        $employees = $this->createEmployees(
            $input->getOption('employee-amount'),
            $salutations,
            $progress,
            $context
        );

        $io->writeln('Mapping employees to customers');
        $this->mapEmployeesToCustomers(
            $customerIds,
            $employees,
            $context
        );

        $io->success('Finished');

        return 0;
    }

    private function createCustomers(
        int $limit,
        string $storefrontUrl,
        array $countries,
        ProgressBar $progress,
        SalesChannelContext $context,
    ): array {
        $customerIds = [];

        $progress->start();

        for ($i = 0; $i <= $limit; ++$i) {
            try {
                $customerResponse = $this->registerRoute->register(
                    $this->createCustomerDataBag(
                        $storefrontUrl,
                        $countries[random_int(0, count($countries) - 1)],
                    ),
                    $context
                );

                $customerIds[] = $customerResponse->getCustomer()->getId();
            } catch (\Throwable $e) {
                // Ignore
            }

            if ($i % 10 === 0) {
                $progress->advance(10);
            }
        }

        $progress->finish();

        return $customerIds;
    }

    private function createEmployees(
        int $limit,
        array $salutations,
        ProgressBar $progress,
        SalesChannelContext $context,
    ): array {
        $progress->start();

        $employees = [];

        for ($i = 0; $i <= $limit; ++$i) {
            $employees[] = $this->prepareEmployee($salutations[random_int(0, count($salutations) - 1)]);
            $progress->advance();
        }

        $this->employeeRepository->upsert($employees, $context->getContext());
        $progress->finish();

        return $employees;
    }

    private function mapEmployeesToCustomers(
        array $customerIds,
        array $employees,
        SalesChannelContext $context,
    ): void {
        $mapping = [];

        foreach ($customerIds as $customerId) {
            $mapping = array_merge(
                array_map(
                    function (string $employeeId) use ($customerId) {
                        return [
                            'id'           => Uuid::randomHex(),
                            'customerId'   => $customerId,
                            'employeeId'   => $employeeId,
                            'admin'        => true,
                            'active'       => true,
                            'customFields' => [
                                'b2b_show_bonus' => false,
                            ],
                        ];
                    },
                    array_column($employees, 'id'),
                ),
                $mapping
            );
        }

        $this->employeeCustomerRepository->upsert($mapping, $context->getContext());
    }

    private function getStorefrontUrl(
        SymfonyStyle $io,
        SalesChannelDomainCollection $domains,
    ): string {
        return $io->choice(
            'Select storefront URL',
            array_values($domains->map(function (SalesChannelDomainEntity $domain) {
                return $domain->getUrl();
            }))
        );
    }

    private function getSalesRep(
        SymfonyStyle $io,
        CustomerCollection $salesReps,
    ): string {
        return $io->choice(
            'Select sales rep',
            array_values($salesReps->map(function (CustomerEntity $salesRep) {
                return $salesRep->getEmail();
            }))
        );
    }

    private function getSalesRepId(
        string $email,
        CustomerCollection $salesReps,
    ): string {
        return $salesReps->filter(function (CustomerEntity $salesRep) use ($email) {
            return $salesRep->getEmail() === $email;
        })->first()->getId();
    }

    private function getSalesChannelId(
        string $url,
        SalesChannelDomainCollection $domains,
    ): string {
        return $domains->filter(function (SalesChannelDomainEntity $domain) use ($url) {
            return $domain->getUrl() === $url;
        })->first()->getSalesChannelId();
    }

    private function addSalesRepCustomer(
        string $salesRepId,
        array $customerIds,
        Context $context,
    ): void {
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
            'id'           => Uuid::randomHex(),
            'firstName'    => $faker->firstName(),
            'lastName'     => $faker->lastName(),
            'email'        => $faker->email(),
            'password'     => 'b2bsellers',
            'salutationId' => $salutationId,
            'customFields' => [
                'b2b_url_login_authentication_hash' => Uuid::randomHex(),
            ],
        ];
    }

    private function updateCustomerCustomFields(
        array $ids,
        Context $context,
    ): void {
        $this->customerRepository->update(
            array_map(
                function (string $id) {
                    return [
                        'id'           => $id,
                        'customFields' => [
                            'b2b_platform_access'      => true,
                            'b2b_sales_representative' => false,
                            'b2b_supervisor'           => false,
                        ],
                    ];
                },
                $ids
            ),
            $context
        );
    }

    private function createCustomerDataBag(
        string $storefrontUrl,
        string $countryId,
    ): RequestDataBag {
        $faker = $this->getFaker();
        $data  = new RequestDataBag();

        $firstName = $faker->firstName();
        $lastName  = $faker->lastName();
        $company   = $faker->company();

        $data->set('guest', false);
        $data->set('company', $company);
        $data->set('firstName', $firstName);
        $data->set('lastName', $lastName);
        $data->set('email', $faker->email());
        $data->set('password', 'b2bsellers');
        $data->set('storefrontUrl', $storefrontUrl);
        $data->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);

        $data->set(
            'billingAddress',
            $this->getAddress(
                $countryId,
                $firstName,
                $lastName,
                $company
            )
        );

        return $data;
    }

    private function getAddress(
        string $countryId,
        string $firstName,
        string $lastName,
        string $company,
    ): RequestDataBag {
        $faker = $this->getFaker();

        return new RequestDataBag([
            'countryId' => $countryId,
            'street'    => $faker->streetAddress(),
            'zipcode'   => $faker->postcode(),
            'city'      => $faker->city(),
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'company'   => $company,
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
