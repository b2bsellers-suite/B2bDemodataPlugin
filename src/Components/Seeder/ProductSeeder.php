<?php

namespace B2bDemodata\Components\Seeder;

use DirectoryIterator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductSeeder
{

    private ContainerInterface $container;
    private Context $context;
    private $nonWriteableAttributes = [
        'displayGroup',
        'sales',
        'availableStock',
        'categoryTree',
        'streamIds',
        'cheapestPrice',
        'orderLineItems',
        'cheapestPriceContainer',
        'streams',
        '_uniqueIdentifier',
        'versionId',
        'createdAt',
        'updatedAt',
        'childCount',
        'categoriesRo',
        'categoryIds',
        'tagIds',
        'optionIds',
        'propertyIds',
        'ratingAverage',
        'available',
        'autoIncrement',
        'extensions',

    ];

    public function __construct(ContainerInterface $container, Context $context)
    {
        $this->container = $container;
        $this->context = $context;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $this->ensureDemoCategoryIsCreated();
        $this->createProducts();
    }

    private function ensureDemoCategoryIsCreated(): void
    {
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
                    'name' => 'Demo-Produkte',
                ]
            ]
            , $this->context
        );
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

        $productJson = $this->removeNonWriteable($productJson);
        $productJson = $this->replaceKnownIds($productJson);
        $productRepository->upsert([
            $productJson
        ],
            $this->context
        );
    }

    private function productExists(string $id): bool
    {
        /** @var EntityRepositoryInterface $repository */
        $productRepository = $this->container->get('product.repository');
        return null !== $productRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $id)), $this->context)->first();
    }

    private function replaceKnownIds(array $productJson):array
    {
        $productJson['taxId'] = $this->getDefaultId('tax');
        $productJson['categories'] = [["id" => SeederConstants::DEMO_CATEGORY_UID]];
        return $productJson;
    }

    private function getDefaultId(string $repoName):string
    {
        /** @var EntityRepositoryInterface $repository */
        $productRepository = $this->container->get($repoName.'.repository');
        return $productRepository->search((new Criteria()), $this->context)->first()->getId();
    }

    private function removeNonWriteable($productJson)
    {
        foreach ($this->nonWriteableAttributes as $attribute){
            if(array_key_exists($attribute, $productJson)) {
                unset($productJson[$attribute]);
            }
        }
        foreach ($productJson as $key => $value){
            if($value === null){
                unset($productJson[$key]);
            }
        }
        return $productJson;
    }


}
