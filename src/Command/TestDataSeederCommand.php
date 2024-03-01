<?php

namespace B2bDemodata\Command;

use B2bDemodata\Components\Seeder\Seeder;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestDataSeederCommand extends Command
{
	protected static $defaultName = 'b2b:test-data:create';

	public function __construct(
		private ContainerInterface                 $container,
        private SystemConfigService                $configService,
        private AbstractSalesChannelContextFactory $contextFactory,
        private Seeder                             $seeder
	)
	{
		parent::__construct();
	}

	/*
	 * Todo's
	 * 	1. add option -f / force
	 */

	protected function execute(InputInterface $input, OutputInterface $output): int
    {
		$ioHelper = new SymfonyStyle($input, $output);
		$delivery = $this->configService->get('core.mailerSettings.disableDelivery');

		if ($delivery) {
			$question = $ioHelper->askQuestion(new ConfirmationQuestion('Mail sender is active! Do you want to proceed?', false));
			if (!$question) {

				return 0;
			}
		}

		$ioHelper->section('Creating test data');
		try {
			$this->seeder->run();
			$ioHelper->success('Completed!!');
		} catch (\Exception $e) {
			$ioHelper->error($e->getMessage());
		}

		return 0;
	}
}
