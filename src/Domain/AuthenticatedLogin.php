<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

/**
 * What the shop's own authentication produced after a successful password check.
 * Held back until the second factor is verified.
 */
final readonly class AuthenticatedLogin
{
    public function __construct(
        public string $userId,
        public string $loginToken,
    ) {
    }
}
