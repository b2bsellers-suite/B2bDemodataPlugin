<?php

namespace B2bDemodata\Command;

use B2bDemodata\Components\Deseeder\Deseeder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'b2b:test-data:reset',
)]
class ResetTestDataCommand extends Command
{
	public function __construct(
        private EntityRepository $customerRepository,
        private EntityRepository $employeeRepository,
        private EntityRepository $employeeCustomerRepository,
        private EntityRepository $productRepository,
        private ContainerInterface $container,
    ) {
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
                $ioHelper,
                $this->container
            ))->run();
            $ioHelper->success('Completed!!');
        } catch (\Exception $e) {
            $ioHelper->error($e->getMessage());
        }

        return self::SUCCESS;
    }
}
