<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

/**
 * Makes the unified namespace classes (OxidEsales\Eshop\...) known to PHPStan outside of a booted shop.
 * The generator writes them to directories whose names contain backslashes, which PHPStan cannot resolve, so
 * they are copied once into a regular directory tree.
 */
$generatedClasses = __DIR__ . '/../../vendor/oxid-esales/oxideshop-unified-namespace-generator/generated';
$unifiedClasses = sys_get_temp_dir() . '/oxid2fa-phpstan-unified-namespace';
if (is_dir($generatedClasses) && !is_dir($unifiedClasses)) {
    foreach (new DirectoryIterator($generatedClasses) as $namespaceDirectory) {
        if ($namespaceDirectory->isDot() || !$namespaceDirectory->isDir()) {
            continue;
        }

        $target = $unifiedClasses . '/' . str_replace('\\', '/', $namespaceDirectory->getFilename());
        mkdir($target, 0777, true);
        foreach (new DirectoryIterator($namespaceDirectory->getPathname()) as $file) {
            if ($file->isFile()) {
                copy($file->getPathname(), $target . '/' . $file->getFilename());
            }
        }
    }
}

spl_autoload_register(static function (string $class) use ($unifiedClasses): void {
    if (!str_starts_with($class, 'OxidEsales\\Eshop\\')) {
        return;
    }

    $file = $unifiedClasses . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

class_alias(
    \OxidEsales\Eshop\Application\Controller\Admin\LoginController::class,
    \DaziWeb\Oxid2Fa\Controller\Admin\LoginController_parent::class
);

class_alias(
    \OxidEsales\Eshop\Core\ViewConfig::class,
    \DaziWeb\Oxid2Fa\Integration\Oxid\ViewConfig_parent::class
);

class_alias(
    \OxidEsales\Eshop\Application\Model\User::class,
    \DaziWeb\Oxid2Fa\Integration\Oxid\User_parent::class
);
