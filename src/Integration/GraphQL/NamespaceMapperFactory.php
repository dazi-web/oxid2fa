<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\GraphQL;

use OxidEsales\GraphQL\Base\Framework\NamespaceMapperInterface;

/**
 * Tells the GraphQL base module where this module keeps its queries.
 *
 * It is a factory because the shop builds every service of a module when the module is activated, also in shops
 * without the GraphQL base module. A class that implements an interface of a missing module would break that, so the
 * interface is only touched in here, and only if it exists. Without the module nothing reads the result.
 */
final class NamespaceMapperFactory
{
    public static function create(): object
    {
        if (!interface_exists(NamespaceMapperInterface::class)) {
            return new \stdClass();
        }

        return new class implements NamespaceMapperInterface {
            public function getControllerNamespaceMapping(): array
            {
                return [
                    'DaziWeb\\Oxid2Fa\\Integration\\GraphQL\\Controller' => __DIR__ . '/Controller/',
                ];
            }

            public function getTypeNamespaceMapping(): array
            {
                return [];
            }
        };
    }
}
