<?php

declare(strict_types=1);

use Frosh\Rector\Set\ShopwareSetList;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

require_once 'vendor/autoload.php';

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSets([
        ShopwareSetList::SHOPWARE_6_6_0,
        ShopwareSetList::SHOPWARE_6_7_0
    ])
;
