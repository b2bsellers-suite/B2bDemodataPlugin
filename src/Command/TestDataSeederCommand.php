<?php

namespace B2bDemodata\Command;

use B2bDemodata\Components\Seeder\Seeder;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'b2b:test-data:create',
)]
class TestDataSeederCommand extends Command
{
    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly Seeder              $seeder
    ) {
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
            $this->seeder->run($output);
            $ioHelper->success('Completed!!');
        } catch (\Exception $e) {
            $ioHelper->error($e->getMessage());
        }

        return 0;
    }
}
