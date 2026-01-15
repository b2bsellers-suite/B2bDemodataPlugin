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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'b2b:test-data:create:product-list',
)]
class ProductListGenerateCommand extends Command
{
    public function __construct(
        private readonly ?EntityRepository $productListRepository,
        private readonly ?EntityRepository $productListTypeRepository,
        private readonly EntityRepository $productRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('customerId', InputOption::VALUE_REQUIRED, 'Customer UUID');
        $this->addArgument('salesChannelId', InputOption::VALUE_REQUIRED, 'Sales Channel UUID');
        $this->addOption('list-amount', 'l', InputOption::VALUE_OPTIONAL, 'How many product lists should be generated', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->productListRepository === null || $this->productListTypeRepository === null) {
            $io->error('Please activate the product lists addon in the B2Bsellers Suite Core plugin.');

            return 1;
        }

        $context = Context::createDefaultContext();

        $lists             = [];
        $defaultListTypeId = $this->getDefaultListTypeId($context);
        $productIds        = $this->productRepository->searchIds(new Criteria(), $context);

        $progress = $io->createProgressBar($input->getOption('list-amount'));
        $progress->start();
        $progress->setMessage('Generating data');

        for ($i = 0; $i <= $input->getOption('list-amount'); ++$i) {
            $list = $this->getBaseData(
                $input->getArgument('customerId'),
                $input->getArgument('salesChannelId'),
                $defaultListTypeId,
                $this->getItems($productIds->getIds())
            );

            $list['name'] = $this->getFaker()->sentence();

            $lists[] = $list;
            $progress->advance();
        }

        $progress->setMessage('Saving data');
        $this->productListRepository->upsert($lists, $context);

        $progress->setMessage('Done');
        $progress->finish();

        return 0;
    }

    private function getItems(array $productIds): array
    {
        $items = [];

        for ($i = 0; $i < count($productIds) * 5; ++$i) {
            $items = array_merge($items, array_map(fn(string $productId) => [
                'id'        => Uuid::randomHex(),
                'productId' => $productId,
            ], $productIds));
        }

        return $items;
    }

    private function getDefaultListTypeId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'default'));

        return $this->productListTypeRepository->searchIds($criteria, $context)->firstId();
    }

    private function getBaseData(string $customerId, string $salesChannelId, string $listTypeId, array $items): array
    {
        return [
            'id'             => Uuid::randomHex(),
            'customerId'     => $customerId,
            'salesChannelId' => $salesChannelId,
            'listTypeId'     => $listTypeId,
            'items'          => $items,
        ];
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        return $faker;
    }
}
