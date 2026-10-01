<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

final readonly class UserAccount
{
    public function __construct(
        public string $userId,
        public string $loginName,
    ) {
    }
}
