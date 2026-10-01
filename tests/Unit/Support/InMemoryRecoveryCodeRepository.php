<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DateTimeImmutable;
use DaziWeb\Oxid2Fa\Application\RecoveryCodeRepository;

final class InMemoryRecoveryCodeRepository implements RecoveryCodeRepository
{
    /** @var array<string, array<string, bool>> enrollment => hash => used */
    private array $codes = [];

    public function replaceAll(string $enrollmentId, array $hashes): void
    {
        $this->codes[$enrollmentId] = array_fill_keys($hashes, false);
    }

    public function consume(string $enrollmentId, string $hash, DateTimeImmutable $at): bool
    {
        if (!isset($this->codes[$enrollmentId][$hash]) || $this->codes[$enrollmentId][$hash]) {
            return false;
        }
        $this->codes[$enrollmentId][$hash] = true;

        return true;
    }

    public function countUnused(string $enrollmentId): int
    {
        return count(array_filter($this->codes[$enrollmentId] ?? [], static fn (bool $used) => !$used));
    }
}
