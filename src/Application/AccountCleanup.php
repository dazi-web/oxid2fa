<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * Removes everything this module keeps about an account that no longer exists: the encrypted secret, recovery
 * codes (they follow the enrolment), the requirement flag and the attempt counter.
 */
final readonly class AccountCleanup
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private RequirementRepository $requirements,
        private ChallengeThrottle $throttle,
    ) {
    }

    public function forget(string $userId): void
    {
        $this->enrollments->delete($userId);
        $this->requirements->setRequired($userId, false);
        $this->throttle->reset($userId);
    }
}
