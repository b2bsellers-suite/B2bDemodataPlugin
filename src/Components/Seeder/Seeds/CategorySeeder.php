<?php

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CategorySeeder
{

	private ContainerInterface $container;
	private Context $context;
	private Connection $connection;
	private array $cachedLanguages = [];


	public function __construct(
		ContainerInterface $container,
		Connection         $connection
	)
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
		echo "\n\nCreating category...\n";
		if (!$this->demoCategoryExist()) {
			$this->createDemoCategory();
		}
	}

	private function demoCategoryExist(): bool
	{
		/** @var EntityRepositoryInterface $repository */
		$categoryRepository = $this->container->get('category.repository');
		return null !== $categoryRepository->search((new Criteria())->addFilter(new EqualsFilter('id', SeederConstants::DEMO_CATEGORY_UID)), $this->context)->first();
	}

	private function createDemoCategory(): void
	{
		/** @var EntityRepositoryInterface $repository */
		$categoryRepository = $this->container->get('category.repository');

		$categoryRepository->create([
				[
					'id' => SeederConstants::DEMO_CATEGORY_UID,
					'parentId' => $this->getDefaultSalesChannel()->getNavigationCategoryId(),
					'parentVersionId' => $this->getDefaultSalesChannel()->getNavigationCategoryVersionId(),
					'translations' => [
						'de-DE' => [
							'name' => 'Demo Produkte',
							'description' => 'In dieser Kategorie befinden sich alle Beispiel Produkte der B2Bsellers Suite. Dabei sind die Produkte so benannt wie die technische Funktion heißt. So ist es einfach die Funktionen zu finden und zu testen.'
						],
						'en-GB' => [
							'name' => 'Demo products',
							'description' => 'This category contains all sample products of the B2Bsellers Suite. The products are named like the technical function. This makes it easy to find and test the functions.'
						],
						'nl-NL' => [
							'name' => 'Demoproducten',
							'description' => 'Deze categorie bevat alle voorbeeldproducten van de B2Bsellers Suite. De producten zijn genoemd naar de technische functie. Dit maakt het gemakkelijk om de functies te vinden en te testen.'
						],
						'da-DK' => [
							'name' => 'Demo produkter',
							'description' => 'Denne kategori indeholder alle prøveprodukter fra B2Bsellers Suite. Produkterne er opkaldt efter den tekniske funktion. Det gør det nemt at finde og teste funktionerne.'
						],
						'pl-PL' => [
							'name' => 'Produkty demonstracyjne',
							'description' => 'Ta kategoria zawiera wszystkie przykładowe produkty pakietu B2Bsellers Suite. Produkty są nazwane według funkcji technicznych. Ułatwia to wyszukiwanie i testowanie funkcji.'
						],
					]

				]
			]
			, $this->context
		);
		echo "Demo category created\n";
	}

	private function getLanguageIdByCode(string $code)
	{
		if (isset($this->cachedLanguages[$code])) {
			return $this->cachedLanguages[$code];
		}

		/** @var string|null $langId */
		$langId = $this->connection->fetchOne('
        SELECT HEX(`language`.`id`) FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

		if (!$langId) {
			return null;
		}
		$this->cachedLanguages[$code] = $langId;

		return $langId;
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

}
