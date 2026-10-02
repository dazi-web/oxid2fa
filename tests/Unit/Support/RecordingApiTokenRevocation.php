<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\ApiTokenRevocation;

final class RecordingApiTokenRevocation implements ApiTokenRevocation
{
    /** @var list<string> */
    public array $revoked = [];

    public function revokeAllFor(string $userId): void
    {
        $this->revoked[] = $userId;
    }
}
