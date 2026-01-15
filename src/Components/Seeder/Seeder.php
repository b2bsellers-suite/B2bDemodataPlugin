<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder;

use B2bDemodata\Components\Seeder\Helper\B2bLicenceTrait;
use B2bDemodata\Components\Seeder\Seeds\CategorySeeder;
use B2bDemodata\Components\Seeder\Seeds\CustomerSeeder;
use B2bDemodata\Components\Seeder\Seeds\OrderSeeder;
use B2bDemodata\Components\Seeder\Seeds\ProductListSeeder;
use B2bDemodata\Components\Seeder\Seeds\ProductSeeder;
use Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Seeder
{
    use B2bLicenceTrait;

    private Context $context;

    public function __construct(
        private ContainerInterface $container,
        private AbstractSalesChannelContextFactory $contextFactory,
        private CategorySeeder $categorySeeder,
        private CustomerSeeder $customerSeeder,
        private ProductSeeder $productSeeder,
        private ProductListSeeder $productListSeeder,
        private OrderSeeder $orderSeeder,
    ) {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws \Exception
     */
    public function run(OutputInterface $output): void
    {
        $this->customerSeeder->run($output);
        $this->categorySeeder->run($output);
        $this->productSeeder->run($output);
        $this->orderSeeder->run($output);

        if ($this->isB2bAddonEnabled($this->container, 'B2bProductLists')) {
            $this->productListSeeder->run($output);
        }

        // ToDo: We will add more seeders like:
        // (new CustomerSpecificPrice($this->container, $this->context))->run();
        // (new OrderSeeder($this->container, $this->context))->run();
        // (new CostCenter($this->container, $this->context))->run();
        // (new Budget($this->container, $this->context))->run();
        // (new Offer($this->container, $this->context))->run();
        // (new CustomRoleForCompany($this->container, $this->context))->run();
    }
}
