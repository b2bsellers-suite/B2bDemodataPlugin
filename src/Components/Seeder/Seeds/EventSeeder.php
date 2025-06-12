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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventSeeder
{
    private string $defaultCustomerId = '';
    private Context $context;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Connection         $connection
    ) {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws \Exception
     */
    public function run(OutputInterface $output): void
    {
        $output->writeln('Creating events...');

        $this->createProducts($output);
    }

    /**
     * @throws Exception
     */
    private function createProducts(OutputInterface $output): void
    {
        $resourceDir = $this->container->get('kernel')->locateResource('@B2bDemodata/Resources');
        $dir = new DirectoryIterator($resourceDir . '/testdata/Events/');

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                try {
                    $eventJson = json_decode(file_get_contents($fileInfo->getRealPath()), true);
                    $eventJson = $this->replaceKnownIds($eventJson);
                    $this->createEvent($eventJson);

                    $output->writeln(sprintf('Created event: %s ✅', $eventJson['name']));
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

        $eventJson['eventFormatId']   = $this->getEventFormatId($eventJson['eventFormatName']);
        $eventJson['eventLocationId'] = $this->getEventLocationId($eventJson['eventLocationName']);
        $eventJson['eventLevelId']    = $this->getEventLevelId($eventJson['eventLevelName']);
        $eventJson['stateId']         = $this->getEventStateId(
            $eventJson['stateName'],
            'b2bsellers_event.state'
        );

        foreach ($eventJson['eventParticipants'] as $key => $eventParticipant) {
            $eventJson['eventParticipants'][$key]['stateId']        = $this->getEventStateId(
                $eventParticipant['stateName'],
                'b2bsellers_event_participant.state'
            );

            $eventJson['eventParticipants'][$key]['paymentStateId'] = $this->getEventStateId(
                $eventParticipant['paymentStateName'],
                'b2bsellers_event_participant_payment.state'
            );
        }

        $eventRepository->upsert([
            $eventJson
        ],
            $this->context
        );
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
        if ($this->defaultCustomerId === '') {
            $customer = $this->getCustomerByEmail(SeederConstants::DEFAULT_CUSTOMER_EMAIL);

            if (!$customer instanceof CustomerEntity) {
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

    private function getEventFormatId(mixed $eventFormatName): string
    {
        $idResult = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`b2bsellers_event_format_id`) 
                FROM `b2bsellers_event_format_translation` 
                WHERE `name` = :name
            SQL,
            [
                'name' => $eventFormatName
            ]
        );

        if ($idResult) {
            return strtolower($idResult);
        }

        throw new \Exception('unable to find event format id for Event creation ' . $eventFormatName . ' not found');
    }

    private function getEventLocationId(mixed $eventLocationName)
    {
        $idResult = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`b2bsellers_event_location_id`) 
                FROM `b2bsellers_event_location_translation` 
                WHERE `name` = :name
            SQL,
            [
                'name' => $eventLocationName
            ]
        );

        if ($idResult) {
            return strtolower($idResult);
        }

        throw new \Exception('unable to find event location id for Event creation ' . $eventLocationName . ' not found');
    }

    private function getEventLevelId(mixed $eventLevelName)
    {
        $idResult = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`b2bsellers_event_level_id`) 
                FROM `b2bsellers_event_level_translation` 
                WHERE `name` = :name
            SQL,
            [
                'name' => $eventLevelName
            ]
        );

        if ($idResult) {
            return strtolower($idResult);
        }

        throw new \Exception('unable to find event level id for Event creation ' . $eventLevelName . ' not found');
    }

    private function getEventStateId(mixed $stateName, string $stateMachineName)
    {
        $stateMachineId = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`id`) 
                FROM `state_machine` 
                WHERE `technical_name` = :name
            SQL,
            [
                'name' => $stateMachineName
            ]
        );


        if (!$stateMachineId) {
            throw new \Exception('unable to find state maschine id for Event creation ' . $stateMachineName . ' not found');
        }

        $idResult = $this->connection->fetchOne(
            <<<SQL
                SELECT HEX(`id`) 
                FROM `state_machine_state`
                WHERE `state_machine_id` = UNHEX(:stateMachineId) 
                  AND `technical_name` = :name
            SQL,
            [
                'stateMachineId' => $stateMachineId,
                'name' => $stateName
            ]
        );

        if ($idResult) {
            return strtolower($idResult);
        }

        throw new \Exception('unable to find event state id for Event creation ' . $stateName . ' not found');

    }
}
