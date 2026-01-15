<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductSeeder
{
    private const PRICE_FIELDS = [
        'price',
        'purchasePrices',
    ];
    private Context $context;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Connection $connection,
    ) {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws \Exception
     */
    public function run(OutputInterface $output): void
    {
        $output->writeln('Creating products...');

        $this->createProducts($output);
    }

    /**
     * @throws \Exception
     */
    private function createProducts(OutputInterface $output): void
    {
        $resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
        $dir         = new \DirectoryIterator($resourceDir . '/testdata/Products/');

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                try {
                    $productJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
                    $this->createProduct($productJson);

                    $output->writeln(sprintf('Created product: %s ✅', $productJson['name']));
                } catch (\Exception $e) {
                    throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
                }
            }
        }
    }

    private function createProduct($productJson): void
    {
        /** @var EntityRepository $productRepository */
        $productRepository = $this->container->get('product.repository');

        $productJson = $this->replaceKnownIds($productJson);
        $productJson = $this->replaceLanguageCodes($productJson);
        $productJson = $this->replaceCurrencyCodes($productJson);

        $productRepository->upsert([
            $productJson,
        ],
            $this->context
        );
    }

    private function replaceKnownIds(array $productJson): array
    {
        $productJson['taxId']      = $this->getDefaultId('tax');
        $productJson['categories'] = [['id' => SeederConstants::DEMO_CATEGORY_UID]];

        if (!$this->isVisibleInSalesChannel($productJson, $this->getDefaultSalesChannel())) {
            $productJson['visibilities'] = [
                [
                    'salesChannelId' => $this->getDefaultId('sales_channel'),
                    'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ];
        }

        return $productJson;
    }

    private function getDefaultId(string $repoName): string
    {
        /** @var EntityRepository $repository */
        $productRepository = $this->container->get($repoName . '.repository');

        return $productRepository->search(new Criteria(), $this->context)->first()->getId();
    }

    private function replaceLanguageCodes(array $productJson): array
    {
        if (!isset($productJson['translations'])) {
            return $productJson;
        }
        $newTranslations = [];
        foreach ($productJson['translations'] as $translation) {
            $newTranslations[$translation['languageCode']] = $translation;
        }
        $productJson['translations'] = $newTranslations;

        return $productJson;
    }

    /**
     * @throws \Exception
     */
    private function replaceCurrencyCodes(array $productJson): array
    {
        if (!isset($productJson['price'])) {
            return $productJson;
        }

        foreach (self::PRICE_FIELDS as $field) {
            if (!array_key_exists($field, $productJson)) {
                continue;
            }

            foreach ($productJson[$field] as $key => $price) {
                if (!isset($price['currencyCode'])) {
                    continue;
                }

                $productJson[$field][$key]['currencyId'] = $this->getCurrentCurrencyId($price['currencyCode']);
                unset($productJson[$field][$key]['currencyCode']);
            }
        }

        return $productJson;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getCurrentCurrencyId($currencyCode): string
    {
        /** @var null|string $currencyId */
        $currencyId = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`currency`.`id`) 
                FROM `currency` 
                WHERE `currency`.`iso_code` = :currencyCode 
                LIMIT 1
            SQL,
            [
                'currencyCode' => $currencyCode,
            ]
        );

        if (!$currencyId) {
            throw new \Exception("Currency with code $currencyCode not found");
        }

        return strtolower($currencyId);
    }

    private function getDefaultSalesChannel(): ?SalesChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->setLimit(1);
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');

        /** @var EntityRepository $repository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');

        return $salesChannelRepository->search($criteria, $this->context)->first();
    }

    private function isVisibleInSalesChannel(array $productJson, SalesChannelEntity $salesChannel): bool
    {
        if ($productJson['id'] && $salesChannel->getId()) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $productJson['id']));
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannel->getId()));

            /** @var EntityRepository $repository */
            $productVisibilityRepository = $this->container->get('product_visibility.repository');

            if ($productVisibilityRepository->search($criteria, $this->context)->first()) {
                return true;
            }
        }

        return false;
    }
}
