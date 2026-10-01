<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\ChallengeThrottle;

final class InMemoryChallengeThrottle implements ChallengeThrottle
{
    /** @var array<string, int> */
    private array $attempts = [];

    public function reserveAttempt(string $userId): int
    {
        return $this->attempts[$userId] = ($this->attempts[$userId] ?? 0) + 1;
    }

    public function attempts(string $userId): int
    {
        return $this->attempts[$userId] ?? 0;
    }

    public function reset(string $userId): void
    {
        unset($this->attempts[$userId]);
    }
}
