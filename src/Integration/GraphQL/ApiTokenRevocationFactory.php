<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\GraphQL;

use DaziWeb\Oxid2Fa\Application\ApiTokenRevocation;
use DaziWeb\Oxid2Fa\Application\NoApiTokenRevocation;
use OxidEsales\GraphQL\Base\Infrastructure\RefreshTokenRepositoryInterface;
use OxidEsales\GraphQL\Base\Infrastructure\Token;

/**
 * The shop builds every service of a module on activation, also in shops without the GraphQL base module. Its services
 * are passed in as optional, and without them nothing is revoked.
 */
final class ApiTokenRevocationFactory
{
    public static function create(
        ?RefreshTokenRepositoryInterface $refreshTokens,
        ?Token $accessTokens,
    ): ApiTokenRevocation {
        if ($refreshTokens === null || $accessTokens === null) {
            return new NoApiTokenRevocation();
        }

        return new GraphQLTokenRevocation($refreshTokens, $accessTokens);
    }
}
