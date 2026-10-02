<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\GraphQL;

use DaziWeb\Oxid2Fa\Application\ApiTokenRevocation;
use OxidEsales\GraphQL\Base\Infrastructure\RefreshTokenRepositoryInterface;
use OxidEsales\GraphQL\Base\Infrastructure\Token;

/**
 * The same two calls the GraphQL base module makes when a user changes the password.
 *
 * The shop builds every service of a module on activation, also in shops without the GraphQL base module. Its services
 * are therefore optional here, and without them there is no API and nothing to revoke.
 */
final readonly class GraphQLTokenRevocation implements ApiTokenRevocation
{
    public function __construct(
        private ?RefreshTokenRepositoryInterface $refreshTokens,
        private ?Token $accessTokens,
    ) {
    }

    public function revokeAllFor(string $userId): void
    {
        $this->refreshTokens?->invalidateUserTokens($userId);
        $this->accessTokens?->invalidateUserTokens($userId);
    }
}
