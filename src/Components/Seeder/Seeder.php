<?php

namespace B2bDemodata\Components\Seeder;

use B2bDemodata\Components\Seeder\Seeds\CategorySeeder;
use B2bDemodata\Components\Seeder\Seeds\CustomerSeeder;
use B2bDemodata\Components\Seeder\Seeds\ProductSeeder;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Seeder
{
	private AbstractSalesChannelContextFactory $contextFactory;
    private ContainerInterface $container;
	private CategorySeeder $categorySeeder;
	private CustomerSeeder $customerSeeder;
	private ProductSeeder $productSeeder;
    private Context $context;

    public function __construct(
		ContainerInterface $container,
		AbstractSalesChannelContextFactory $contextFactory,
		CategorySeeder	$categorySeeder,
		CustomerSeeder $customerSeeder,
		ProductSeeder $productSeeder
	)
    {
        $this->contextFactory = $contextFactory;
        $this->container = $container;
		$this->categorySeeder = $categorySeeder;
		$this->customerSeeder = $customerSeeder;
		$this->productSeeder = $productSeeder;
        $this->context = Context::createDefaultContext();
    }

    public function run()
    {

		$this->customerSeeder->run();
		$this->categorySeeder->run();
		$this->productSeeder->run();

		// ToDo: We will add more seeders like:
		// (new CustomerSpecificPrice($this->container, $this->context))->run();
		// (new OrderSeeder($this->container, $this->context))->run();
		// (new CostCenter($this->container, $this->context))->run();
		// (new Budget($this->container, $this->context))->run();
		// (new Offer($this->container, $this->context))->run();
		// (new CustomRoleForCompany($this->container, $this->context))->run();
    }
}
