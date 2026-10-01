<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\UserAccount;
use DaziWeb\Oxid2Fa\Application\UserDirectory;

final class FakeUserDirectory implements UserDirectory
{
    /** @var array<string, bool> */
    public array $inactive = [];

    /** @var list<UserAccount> */
    public array $administrators = [];

    public function administrators(): array
    {
        return $this->administrators;
    }

    public function isActive(string $userId): bool
    {
        return !isset($this->inactive[$userId]);
    }

    public function accountLabel(string $userId): string
    {
        return $userId . '@example.com';
    }
}
