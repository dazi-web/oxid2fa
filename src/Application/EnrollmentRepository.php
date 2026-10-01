<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DateTimeImmutable;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

interface EnrollmentRepository
{
    public function find(string $userId): ?Enrollment;

    /** Replaces an unfinished setup; never touches an active enrolment. */
    public function saveSetup(string $userId, string $secretEncrypted): Enrollment;

    /** True only for the call that actually switched the enrolment from setup to active. */
    public function activate(string $enrollmentId, DateTimeImmutable $moment): bool;

    /**
     * Marks a TOTP time step as used. False if this step or a later one was already accepted,
     * which is what rejects a replayed code.
     */
    public function claimStep(string $enrollmentId, int $step): bool;

    /** @return array<string, Enrollment> by user id */
    public function findAll(): array;

    /** @return list<string> every stored (encrypted) secret, for checking them against the current key */
    public function allEncryptedSecrets(): array;

    /** Removes the enrolment together with its recovery codes. */
    public function delete(string $userId): void;
}
