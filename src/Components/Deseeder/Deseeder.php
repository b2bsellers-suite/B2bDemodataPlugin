<?php

namespace B2bDemodata\Components\Deseeder;


use B2bDemodata\Components\Seeder\Helper\SeederConstants;
use B2bSellersCore\Components\B2bConfiguration\Traits\B2bLicenceTrait;
use B2bSellersCore\Components\Employee\EmployeeEntity;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Deseeder
{
    use B2bLicenceTrait;

    const TESTDATA_DIRECTORY = '/../../Resources/testdata';
    private Connection $connection;


    public function __construct(
        private EntityRepository $customerRepository,
        private EntityRepository $employeeRepository,
        private EntityRepository $employeeCustomerRepository,
        private EntityRepository $productRepository,
        private SymfonyStyle     $ioHelper,
        private ContainerInterface $container
    )
    {
        $this->connection = Kernel::getConnection();
    }


    public function run(): bool
    {
        /*
         * Delete all customer & employee relevant test-data
         */
        try {
            $this->deleteCustomersPartialAssortment();
            $this->deleteCustomersBudgets();
            $this->deleteCustomersActivities();
            $this->deleteOffers();
            $this->deleteCustomersCostCenters();
            $this->deleteCustomersSpecificPrices();
            $this->deleteCustomersOrderLists();
            $this->deleteCustomersPasswordlessLogins();
            $this->deleteCustomersAndEmployees();

            /*
             * Delete all product relevant test-data
             */
            $this->deleteCategory();
            $this->deleteProducts();
            if ($this->isB2bAddonEnabled($this->container, 'B2bEventManager')) {
                $this->deleteEvents();
            }
            return true;
        } catch (\Exception $e) {
            $this->ioHelper->error($e->getMessage());
            return false;
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function deleteCustomersAndEmployees(): void
    {
        $this->ioHelper->info('Start deleting customer Customers and employees');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $employees = $this->getEmployees($customerId);
                foreach ($employees as $employee) {
                    $employeeId = $employee->getEmployeeId();

                    try {
                        $this->employeeCustomerRepository->delete([['customerId' => $customerId, 'employeeId' => $employeeId]], Context::createDefaultContext());
                        $this->employeeRepository->delete([['id' => $employeeId]], Context::createDefaultContext());
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                    }
                }
                $this->connection->executeStatement("DELETE FROM `b2bsellers_sales_representative_customer` WHERE `customer_id` = UNHEX('" . $customerId . "')");
                try {
                    $this->customerRepository->delete([['id' => $customerId]], Context::createDefaultContext());
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
                $this->connection->executeStatement("DELETE FROM `customer` WHERE `id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    // get Customer by Mail Address
    private function getCustomerByEmail(string $email): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        return $this->customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    private function getEmployeeByEmail(string $email): ?EmployeeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        return $this->employeeRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    private function getProductByProductId(string $productId): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));
        return $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    private function getEmployees(string $customerId): ?EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('employee');
        return $this->employeeCustomerRepository->search($criteria, Context::createDefaultContext())->getEntities() ?? new EntityCollection();
    }

    private function deleteProducts()
    {
        $this->ioHelper->info('Start deleting customer products');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Products');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $product = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Products/' . $file), true);
            $product = $this->getProductByProductId($product['id']);
            if ($product) {
                $this->productRepository->delete([['id' => $product->getId()]], Context::createDefaultContext());
                $this->connection->executeStatement("DELETE FROM `product_visibility` WHERE `product_id` = UNHEX('" . $product->getId() . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCategory()
    {
        $this->ioHelper->info('Start deleting demo category');
        $this->connection->executeStatement("DELETE FROM `category` WHERE `id` = UNHEX('" . SeederConstants::DEMO_CATEGORY_UID . "')");

    }

    /**
     * @throws Exception
     */
    private function deleteCustomersPartialAssortment()
    {
        $this->ioHelper->info('Start deleting customer assortments');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_customer_partial_assortment_extension` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteOffers()
    {
        $this->ioHelper->info('Start deleting offers');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_offer` WHERE `offer_customer_id` = UNHEX('" . $customerId . "')");
                $this->connection->executeStatement("DELETE FROM `b2bsellers_offer` WHERE `editor_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersBudgets()
    {
        $this->ioHelper->info('Start deleting customer budgets');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_budget` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersActivities()
    {
        $this->ioHelper->info('Start deleting customer activities');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_customer_activity` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersCostCenters()
    {
        $this->ioHelper->info('Start deleting customer cost centers');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_customer_cost_center` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersSpecificPrices()
    {
        $this->ioHelper->info('Start deleting customer specific prices');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_customer_price` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersPasswordlessLogins()
    {
        $this->ioHelper->info('Start deleting customer passwordless logins');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_passwordless_login` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    /**
     * @throws Exception
     */
    private function deleteCustomersOrderLists()
    {
        $this->ioHelper->info('Start deleting customer orderlists');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $customer = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Customers/' . $file), true);
            $customer = $this->getCustomerByEmail($customer['email']);

            if ($customer) {
                $customerId = $customer->getId();
                $this->connection->executeStatement("DELETE FROM `b2bsellers_product_list` WHERE `customer_id` = UNHEX('" . $customerId . "')");
            }
        }
    }

    private function deleteEvents()
    {
        $this->ioHelper->info('Start deleting customer Events');
        $files = scandir(__DIR__ . self::TESTDATA_DIRECTORY . '/Events');
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {

            $event = json_decode(file_get_contents(__DIR__ . self::TESTDATA_DIRECTORY . '/Events/' . $file), true);
            if (!empty($event['id'])) {
                $this->connection->executeStatement("DELETE FROM `b2bsellers_event` WHERE `id` = UNHEX('" . $event['id'] . "')");
            }
        }
    }

}
