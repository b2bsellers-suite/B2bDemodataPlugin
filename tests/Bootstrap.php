<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

if (is_readable(__DIR__ . '/../vendor/shopware/platform/src/Core/TestBootstrapper.php')) {
    require __DIR__ . '/../vendor/shopware/platform/src/Core/TestBootstrapper.php';
} else if (is_readable(__DIR__ . '/../vendor/shopware/core/TestBootstrapper.php')) {
    require __DIR__ . '/../vendor/shopware/core/TestBootstrapper.php';
}

return (new TestBootstrapper())
    ->setProjectDir($_SERVER['PROJECT_ROOT'] ?? dirname(__DIR__, 4))
    ->setLoadEnvFile(true)
    ->setForceInstallPlugins(true)
    ->addCallingPlugin()
    ->bootstrap()
    ->getClassLoader();
