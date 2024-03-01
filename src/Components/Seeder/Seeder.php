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

    private Context $context;

    public function __construct(
		private ContainerInterface $container,
        private AbstractSalesChannelContextFactory $contextFactory,
        private CategorySeeder	$categorySeeder,
        private CustomerSeeder $customerSeeder,
        private ProductSeeder $productSeeder,
        private EventSeeder $eventSeeder
	)
    {
        $this->context = Context::createDefaultContext();
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
