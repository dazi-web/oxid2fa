<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\GraphQL\Controller;

use DaziWeb\Oxid2Fa\Application\SecondFactorSubmission;
use OxidEsales\GraphQL\Base\DataType\LoginInterface;
use OxidEsales\GraphQL\Base\Service\LoginServiceInterface;
use TheCodingMachine\GraphQLite\Annotations\Query;

/**
 * The login service is optional only so that the shop can build this service when the module is activated in a shop
 * without the GraphQL base module; the GraphQL base module never calls this class if it is not there.
 *
 * Sign-in for administrators who have a second factor: username, password and a code in one request. Returns the
 * same access and refresh token as the `login` query of the GraphQL base module; `refresh` renews the access token
 * without a new code.
 */
final class TwoFactorLogin
{
    public function __construct(
        private readonly ?LoginServiceInterface $loginService,
        private readonly SecondFactorSubmission $submission,
    ) {
    }

    /**
     * Query of the two-factor module.
     * Retrieve an access token and a refresh token with username, password and the current code (or a recovery code).
     *
     * @Query
     */
    public function twoFactorLogin(string $username, string $password, string $code): LoginInterface
    {
        if ($this->loginService === null) {
            throw new \LogicException('The GraphQL base module is not installed.');
        }

        $this->submission->provide($code);
        try {
            return $this->loginService->login($username, $password);
        } finally {
            $this->submission->take();
        }
    }
}
