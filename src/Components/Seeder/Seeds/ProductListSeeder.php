<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use B2bProductLists\Components\Service\ProductListsService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Output\OutputInterface;

class ProductListSeeder
{
    private Context $context;

    public function __construct(
        private readonly ?EntityRepository $productListRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
        $this->context = Context::createDefaultContext();
    }

    public function run(OutputInterface $output): void
    {
        if ($this->productListRepository === null) {
            $output->writeln('Product list repository missing, skipping demo data.');

            return;
        }

        $customerId = $this->getCustomerId(SeederConstants::DEFAULT_B2B_CUSTOMER_EMAIL);

        if (!$customerId) {
            $output->writeln('No default B2B customer found for product list demo data.');

            return;
        }

        $salesChannel = $this->getDefaultSalesChannel();

        if (!$salesChannel instanceof SalesChannelEntity) {
            $output->writeln('No active storefront sales channel found for product list demo data.');

            return;
        }

        $defaultTypeId = $this->systemConfigService->get(
            'B2bProductLists.config.productListsDefaultProductListType',
            $salesChannel->getId()
        );

        if (!is_string($defaultTypeId) || $defaultTypeId === '') {
            $output->writeln('Missing product list configuration, skipping.');

            return;
        }

        $productIds = $this->productRepository
            ->searchIds((new Criteria())->setLimit(3), $this->context)
            ->getIds();

        if ($productIds === []) {
            $output->writeln('No products available for product list demo data.');

            return;
        }

        $payload = [];

        $payload[] = $this->buildProductListPayload(
            'Demo Manual Order List',
            $customerId,
            $salesChannel->getId(),
            $defaultTypeId,
            $productIds
        );

        $automatedIndex = 1;

        foreach (ProductListsService::ACTION_TYPE_CONFIG_KEYS as $configKey) {
            $typeId = $this->systemConfigService->get(
                sprintf('B2bProductLists.config.%s', $configKey),
                $salesChannel->getId()
            );

            if (!is_string($typeId) || $typeId === '') {
                continue;
            }

            $payload[] = $this->buildProductListPayload(
                sprintf('Demo Automated Order List %d', $automatedIndex),
                $customerId,
                $salesChannel->getId(),
                $typeId,
                $productIds
            );

            ++$automatedIndex;
        }

        if ($payload === []) {
            return;
        }

        $this->productListRepository->upsert($payload, $this->context);

        $output->writeln(sprintf('Created %d demo product lists.', count($payload)));
    }

    private function buildProductListPayload(
        string $name,
        string $customerId,
        string $salesChannelId,
        string $listTypeId,
        array $productIds
    ): array {
        $items = [];

        foreach (array_values($productIds) as $index => $productId) {
            $items[] = [
                'id'        => Uuid::randomHex(),
                'productId' => $productId,
                'quantity'  => $index + 1,
                'position'  => $index,
            ];
        }

        return [
            'id'            => $this->getExistingProductListId($customerId, $name) ?? Uuid::randomHex(),
            'name'          => $name,
            'customerId'    => $customerId,
            'salesChannelId' => $salesChannelId,
            'listTypeId'    => $listTypeId,
            'items'         => $items,
        ];
    }

    private function getCustomerId(string $email): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('email', $email))
            ->setLimit(1);

        return $this->customerRepository->searchIds($criteria, $this->context)->firstId();
    }

    private function getExistingProductListId(string $customerId, string $name): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $customerId))
            ->addFilter(new EqualsFilter('name', $name))
            ->setLimit(1);

        return $this->productListRepository->searchIds($criteria, $this->context)->firstId();
    }

    private function getDefaultSalesChannel(): ?SalesChannelEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->setLimit(1);

        return $this->salesChannelRepository->search($criteria, $this->context)->first();
    }
}
