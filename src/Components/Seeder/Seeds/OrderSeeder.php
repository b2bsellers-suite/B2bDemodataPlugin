<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Seeds;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderSeeder
{
    private Context $context;

    public function __construct(
        private readonly ContainerInterface   $container,
        private readonly EntityRepository     $orderRepository,
        private readonly EntityRepository     $customerRepository,
        private readonly EntityRepository     $salesChannelRepository,
        private readonly EntityRepository     $currencyRepository,
        private readonly EntityRepository     $employeeRepository,
        private readonly InitialStateIdLoader $initialStateIdLoader,
    )
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws \Exception
     */
    public function run(OutputInterface $output): void
    {
        $output->writeln('Creating orders...');
        $this->createOrders($output);
    }

    /**
     * @throws \Exception
     */
    private function createOrders(OutputInterface $output): void
    {
        $resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
        $dir = new \DirectoryIterator($resourceDir . '/testdata/Orders/');

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                try {
                    $orderJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
                    $this->createOrder($orderJson);
                    $output->writeln('Created order: ' . $orderJson['orderNumber'] . ' ✅ ');
                } catch (\Exception $e) {
                    throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
                }
            }
        }
    }

    private function createOrder($orderJson): void
    {
        $orderJson = $this->addDynamicOderData($orderJson);
        $orderJson = $this->replaceCustomerData($orderJson);
        $orderJson = $this->calculatePrices($orderJson);

        $this->orderRepository->create([
            $orderJson,
        ],
            $this->context
        );
    }

    private function addDynamicOderData(array $orderJson): array
    {
        $orderJson['id'] = Uuid::randomHex();
        $orderJson['salesChannelId'] = $this->getDefaultSalesChannel()->getId();
        $orderJson['currencyId'] = $this->getCurrentCurrencyId($orderJson['currencyIsoCode']);
        $orderJson['itemRounding'] = json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $orderJson['totalRounding'] = json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $orderJson['stateId'] = $this->initialStateIdLoader->get(OrderStates::STATE_MACHINE);
        $orderJson['orderDateTime'] = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $orderJson['price'] = new CartPrice($orderJson['orderTotal'], $orderJson['orderTotal'], 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET);
        $orderJson['shippingCosts'] = new CalculatedPrice($orderJson['shippingCosts'], $orderJson['shippingCosts'], new CalculatedTaxCollection(), new TaxRuleCollection());
        if (!empty($orderJson['employeeMail'])) {
            $orderJson['customFields'] = ['b2b_order_customer_employee_id' => $this->getEmployeeIdByMail($orderJson['employeeMail'])];
        }

        return $orderJson;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getCurrentCurrencyId($currencyCode): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $currencyCode));
        $criteria->setLimit(1);

        return $this->currencyRepository->searchIds($criteria, $this->context)->firstId();
    }

    private function getDefaultSalesChannel(): ?SalesChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->setLimit(1);

        return $this->salesChannelRepository->search($criteria, $this->context)->first();
    }

    private function replaceCustomerData(array $orderJson): array
    {
        $criteria = new Criteria();

        if (!isset($orderJson['orderCustomer']['email'])) {
            throw new \Exception('missing customer email');
        }

        $criteria->addFilter(new EqualsFilter('email', $orderJson['orderCustomer']['email']));
        $customer = $this->customerRepository->search(
            $criteria,
            $this->context
        )->first();
        $orderJson['orderCustomer']['salutationId'] = $customer->getSalutationId();
        $orderJson['orderCustomer']['customerNumber'] = $customer->getCustomerNumber();
        $orderJson['orderCustomer']['customer']['id'] = $customer->getId();
        $orderJson['orderCustomer']['customer']['salesChannelId'] = $customer->getSalesChannelId();
        $orderJson['orderCustomer']['shippingAddressId'] = $customer->getDefaultShippingAddressId();
        $orderJson['billingAddressId'] = $orderJson['orderCustomer']['billingAddressId'] = $customer->getDefaultShippingAddressId();

        return $orderJson;
    }

    private function calculatePrices(array $orderJson): array
    {
        foreach ($orderJson['lineItems'] as $key => $orderItem) {
            $price = $orderItem['price'];
            $orderItem['id'] = Uuid::randomHex();
            $orderItem['price'] = new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection());
            $orderItem['priceDefinition'] = new QuantityPriceDefinition($price, new TaxRuleCollection());
            $orderJson['lineItems'][$key] = $orderItem;
        }

        return $orderJson;
    }

    private function getEmployeeIdByMail(string $email): string
    {
        return $this->employeeRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('email', $email)), $this->context)->firstId();
    }
}
