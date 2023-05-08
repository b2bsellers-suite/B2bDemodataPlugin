<?php

namespace B2bDemodata\Components\Seeder;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Seeder
{
    /**
     * @var AbstractSalesChannelContextFactory
     */
    private AbstractSalesChannelContextFactory $contextFactory;
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;
    /**
     * @var \Shopware\Core\Framework\Context
     */
    private Context $context;

    public function __construct(ContainerInterface $container, AbstractSalesChannelContextFactory $contextFactory, Context $context)
    {
        $this->contextFactory = $contextFactory;
        $this->container = $container;
        $this->context = $context;
    }

    public function run()
    {
        (new CustomerSeeder($this->container, $this->contextFactory, $this->context))->run();

      	// ToDo: add category seeder
		// (new CategorySeeder($this->container, $this->contextFactory, $this->context))->run();

        (new ProductSeeder($this->container, $this->context))->run();
    }
}
