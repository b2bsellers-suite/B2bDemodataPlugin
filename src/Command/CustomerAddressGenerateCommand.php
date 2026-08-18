<?php

declare(strict_types=1);

namespace B2bDemodata\Command;

use Faker\Factory;
use Faker\Generator;
use Maltyxx\ImagesGenerator\ImagesGeneratorProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'b2b:test-data:create:customer-address',
)]
class CustomerAddressGenerateCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerAddressRepository,
        private readonly EntityRepository $countryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('customer', InputArgument::REQUIRED, 'Customer e-mail or customer ID');
        $this->addOption('amount', null, InputOption::VALUE_OPTIONAL, 'Number of addresses to generate', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $customerIdentifier = $input->getArgument('customer');
        $amount             = (int) $input->getOption('amount');

        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $customerIdentifier)),
            $context
        )->first();

        if ($customer === null) {
            $customer = $this->customerRepository->search(
                (new Criteria())->addFilter(new EqualsFilter('id', $customerIdentifier)),
                $context
            )->first();
        }

        if ($customer === null) {
            $io->error(sprintf('Customer "%s" not found.', $customerIdentifier));

            return Command::FAILURE;
        }

        $customerId = $customer->getId();
        $countryIds = $this->countryRepository->searchIds(new Criteria(), $context)->getIds();

        $addresses = [];

        for ($i = 0; $i < $amount; ++$i) {
            $faker       = $this->getFaker();
            $addresses[] = [
                'id'         => Uuid::randomHex(),
                'customerId' => $customerId,
                'countryId'  => $countryIds[random_int(0, count($countryIds) - 1)],
                'firstName'  => $faker->firstName(),
                'lastName'   => $faker->lastName(),
                'company'    => $faker->company(),
                'street'     => $faker->streetAddress(),
                'zipcode'    => $faker->postcode(),
                'city'       => $faker->city(),
            ];
        }

        $this->customerAddressRepository->create($addresses, $context);

        $io->success(sprintf('Created %d address(es) for customer "%s".', $amount, $customerIdentifier));

        return Command::SUCCESS;
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        return $faker;
    }
}
