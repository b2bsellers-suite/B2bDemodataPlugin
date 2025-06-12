<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MapCustomerData implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::MAPPING_REGISTER_CUSTOMER => 'mapCustomerData',
        ];
    }

    public function mapCustomerData(
        DataMappingEvent $event
    ): void {
        $dataBag = $event->getInput();
        $customer = $event->getOutput();

        if (empty($dataBag->get('customerNumber'))) {
            return;
        }

        $customer['customerNumber'] = $dataBag->get('customerNumber');

        $event->setOutput($customer);
    }

}
