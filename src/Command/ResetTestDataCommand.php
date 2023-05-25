<?php

namespace B2bDemodata\Command;

use B2bDemodata\Components\Deseeder\Deseeder;
use B2bDemodata\Components\Seeder\Seeder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResetTestDataCommand extends Command
{
	protected static $defaultName = 'b2b:test-data:reset';

	private EntityRepository $customerRepository;
	private EntityRepository $employeeRepository;
	private EntityRepository $employeeCustomerRepository;
	private EntityRepository $productRepository;

	public function __construct(
		EntityRepository $customerRepository,
		EntityRepository $employeeRepository,
		EntityRepository $employeeCustomerRepository,
		EntityRepository $productRepository
	)
	{
		$this->customerRepository = $customerRepository;
		$this->employeeRepository = $employeeRepository;
		$this->employeeCustomerRepository = $employeeCustomerRepository;
		$this->productRepository = $productRepository;

		parent::__construct();
	}


	protected function configure(): void
	{
		$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force delete of all test data');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
    {
		$ioHelper = new SymfonyStyle($input, $output);
		$isForce = $input->getOption('force') ?? false;

		if (!$isForce) {
			$question = $ioHelper->askQuestion(new ConfirmationQuestion('Delete all B2Bsellers test data! Do you want to proceed?', false));

			if (!$question) {
				return self::INVALID;
			}
		}

		$ioHelper->section('Start deleting all test data');
		try {
			(new Deseeder(
				$this->customerRepository,
				$this->employeeRepository,
				$this->employeeCustomerRepository,
				$this->productRepository,
				$ioHelper
			))->run();
			$ioHelper->success('Completed!!');
		} catch (\Exception $e) {
			$ioHelper->error($e->getMessage());
		}

		return self::SUCCESS;
	}
}
