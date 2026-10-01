<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DateTimeImmutable;

interface RecoveryCodeRepository
{
    /** @param list<string> $hashes */
    public function replaceAll(string $enrollmentId, array $hashes): void;

    /** Atomic: of several parallel requests with the same code exactly one gets true. */
    public function consume(string $enrollmentId, string $hash, DateTimeImmutable $moment): bool;

    public function countUnused(string $enrollmentId): int;
}
