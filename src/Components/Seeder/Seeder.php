<?php

namespace B2bDemodata\Components\Seeder;

use B2bDemodata\Components\Seeder\Seeds\CategorySeeder;
use B2bDemodata\Components\Seeder\Seeds\CustomerSeeder;
use B2bDemodata\Components\Seeder\Seeds\EventSeeder;
use B2bDemodata\Components\Seeder\Seeds\ProductSeeder;
use B2bSellersCore\Components\B2bConfiguration\Traits\B2bLicenceTrait;
use Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Seeder
{
    use B2bLicenceTrait;
	private AbstractSalesChannelContextFactory $contextFactory;
    private ContainerInterface $container;
	private CategorySeeder $categorySeeder;
	private CustomerSeeder $customerSeeder;
	private ProductSeeder $productSeeder;
    private Context $context;
    private EventSeeder $eventSeeder;

    public function __construct(
		ContainerInterface $container,
		AbstractSalesChannelContextFactory $contextFactory,
		CategorySeeder	$categorySeeder,
		CustomerSeeder $customerSeeder,
		ProductSeeder $productSeeder,
        EventSeeder $eventSeeder
	)
    {
        $this->contextFactory = $contextFactory;
        $this->container = $container;
		$this->categorySeeder = $categorySeeder;
		$this->customerSeeder = $customerSeeder;
		$this->productSeeder = $productSeeder;
        $this->context = Context::createDefaultContext();
        $this->eventSeeder = $eventSeeder;
    }

    /**
     * @throws Exception
     */
    public function run()
    {

		$this->customerSeeder->run();
		$this->categorySeeder->run();
		$this->productSeeder->run();

        if($this->isB2bAddonEnabled($this->container, 'B2bEventManager')) {
            $this->eventSeeder->run();
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
