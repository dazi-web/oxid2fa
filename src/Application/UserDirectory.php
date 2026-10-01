<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

interface UserDirectory
{
    public function isActive(string $userId): bool;

    /** Name shown next to the account in the authenticator app. */
    public function accountLabel(string $userId): string;

    /** @return list<UserAccount> all accounts with back end access */
    public function administrators(): array;
}
