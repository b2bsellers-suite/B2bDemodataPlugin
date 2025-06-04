<?php

declare(strict_types=1);

namespace B2bDemodata\Components\Seeder\Helper;

use B2bSellersCore\Components\B2bConfiguration\Services\B2bSellersLicencesService;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait B2bLicenceTrait
{
    public function isB2bAddonEnabled(ContainerInterface $container, string $addon): bool
    {
        $licenceInformation = $this->getLicenceInformation($container);
        $enabledAddons      = $licenceInformation['addons'];

        return in_array($addon, $enabledAddons);
    }

    public function getB2bLicenceRestriction(ContainerInterface $container, string $name)
    {
        $licenceInformation = $this->getLicenceInformation($container);
        $restrictions       = $licenceInformation['restrictions'];

        return $restrictions[$name] ?? null;
    }

    private function getLicenceInformation(ContainerInterface $container): array
    {
        $service = $container->get(B2bSellersLicencesService::class);

        return $service->loadLicenceInformation();
    }
}
