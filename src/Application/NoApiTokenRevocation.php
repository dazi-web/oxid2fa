<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/** For shops without an API that hands out tokens. */
final class NoApiTokenRevocation implements ApiTokenRevocation
{
    public function revokeAllFor(string $userId): void
    {
    }
}
