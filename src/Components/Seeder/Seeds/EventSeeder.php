<?php

namespace B2bDemodata\Components\Seeder\Seeds;

use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use DirectoryIterator;
use Doctrine\DBAL\Connection;
use Exception;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventSeeder
{


    private string $defaultCustomerId = '';

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
        echo "\n\nCreating events...\n";
        $this->createProducts();
    }

    /**
     * @throws Exception
     */
    private function createProducts(): void
    {
        $resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
        $dir = new DirectoryIterator($resourceDir . '/testdata/Events/');

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                try {
                    $eventJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
                    $eventJson = $this->replaceKnownIds($eventJson);
                    $this->createEvent($eventJson);
                } catch (\Exception $e) {
                    throw new \Exception('error handling ' . $fileInfo->getFilename() . ' ' . $e->getMessage());
                }
            }
        }
    }

    private function createEvent($eventJson): void
    {
        /** @var EntityRepository $eventRepository */
        $eventRepository = $this->container->get('b2bsellers_event.repository');

        $eventRepository->upsert([
            $eventJson
        ],
            $this->context
        );

        echo "Created event: " . $eventJson['name'] . " ✅ \n";

    }

    private function replaceKnownIds(array $eventJson): array
    {
        foreach ($eventJson as $key => $value) {
            if ($key == 'customerId' || $key == 'salesRepresentativeId') {
                $eventJson[$key] = $this->getDefaultCustomer();
            }
            if (is_array($value)) {
                $eventJson[$key] = $this->replaceKnownIds($eventJson[$key]);
            }
        }
        return $eventJson;
    }

    private function getDefaultCustomer(): string
    {
        if ($this->defaultCustomerId == '') {
            $customer = $this->getCustomerByEmail(SeederConstants::DEFAULT_CUSTOMER_EMAIL);
            if(!$customer){
                throw new \Exception('unable to find default customer for Event creation');
            }
            $this->defaultCustomerId = $customer->getId();
        }
        return $this->defaultCustomerId;
    }

    private function getCustomerByEmail(string $email): ?CustomerEntity
    {
        /** @var EntityRepository $customerRepository */
        $customerRepository = $this->container->get('customer.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        return $customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }
}
