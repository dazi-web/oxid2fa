<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * Ends the API sessions (access and refresh tokens) of an account. A token that was issued with the password alone must
 * not outlive the moment the account gets a second factor, because renewing it needs no code.
 */
interface ApiTokenRevocation
{
    public function revokeAllFor(string $userId): void;
}
