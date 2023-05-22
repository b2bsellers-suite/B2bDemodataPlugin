<?php

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use DirectoryIterator;
use Doctrine\DBAL\Connection;
use Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductSeeder
{

	private ContainerInterface $container;
	private Context $context;
	private Connection $connection;

	public function __construct(ContainerInterface $container, Connection $connection)
	{
		$this->container = $container;
		$this->connection = $connection;
		$this->context = Context::createDefaultContext();
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function run()
	{
		echo "\n\nCreating products...\n";
		$this->createProducts();
	}

	private function createProducts(): void
	{
		$resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
		$dir = new DirectoryIterator($resourceDir . '/testdata/Products/');

		foreach ($dir as $fileInfo) {
			if (!$fileInfo->isDot()) {
				try {
					$productJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
					$this->createProduct($productJson);
				} catch (\Exception $e) {
					throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
				}
			}
		}
	}

	private function createProduct($productJson): void
	{
		/** @var EntityRepositoryInterface $productRepository */
		$productRepository = $this->container->get('product.repository');

		$productJson = $this->replaceKnownIds($productJson);
		$productJson = $this->replaceLanguageCodes($productJson);
		$productJson = $this->replaceCurrencyCodes($productJson);

		//dd($productJson);


		$productRepository->upsert([
			$productJson
		],
			$this->context
		);

		echo "Created product: " . $productJson['name'] . "\n";

	}

	private function productExists(string $id): bool
	{
		/** @var EntityRepositoryInterface $repository */
		$productRepository = $this->container->get('product.repository');
		return null !== $productRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $id)), $this->context)->first();
	}

	private function replaceKnownIds(array $productJson): array
	{
		$productJson['taxId'] = $this->getDefaultId('tax');
		$productJson['categories'] = [["id" => SeederConstants::DEMO_CATEGORY_UID]];
		$productJson['visibilities'] = [
			[
				"salesChannelId" => $this->getDefaultId('sales_channel'),
				"visibility" => 30 // there are 3 different visibility modes: Invisible, search only and all. The number 30 stands for all, 20 for search only and 10 for invisible.
			]
		];
		return $productJson;
	}

	private function getDefaultId(string $repoName): string
	{
		/** @var EntityRepositoryInterface $repository */
		$productRepository = $this->container->get($repoName . '.repository');
		return $productRepository->search((new Criteria()), $this->context)->first()->getId();
	}

	private function replaceLanguageCodes(array $productJson)
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

	private function replaceCurrencyCodes(array $productJson)
	{

		if (!isset($productJson['price'])) {
			return $productJson;
		}

		foreach ($productJson['price'] as $key => $price) {
			if (!isset($price['currencyCode'])) {
				continue;
			}

			$productJson['price'][$key]["currencyId"] = $this->getCurrentCurrencyId($price['currencyCode']);
			unset($productJson['price'][$key]["currencyCode"]);
		}

		return $productJson;
	}

	private function getCurrentCurrencyId($currencyCode)
	{
		/** @var string|null $currencyId */
		$currencyId = $this->connection->fetchOne('
        SELECT HEX(`currency`.`id`) FROM `currency` WHERE `currency`.`iso_code` = :currencyCode LIMIT 1
        ', ['currencyCode' => $currencyCode]);

		if (!$currencyId) {
			throw new Exception("Currency with code $currencyCode not found");
		}
		// write all uppercase to small characters currencyid
		$currencyId = strtolower($currencyId);

		return $currencyId;
	}


}
