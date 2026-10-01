<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DateTimeImmutable;

final readonly class AdminOverviewRow
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SETUP = 'setup';
    public const STATUS_NONE = 'none';

    public function __construct(
        public string $userId,
        public string $loginName,
        public string $status,
        public ?DateTimeImmutable $enabledAt,
        public bool $required,
        public int $failedAttempts,
    ) {
    }

    /** The next attempt would be refused. */
    public function isLocked(): bool
    {
        return $this->failedAttempts >= ChallengeThrottle::MAX_ATTEMPTS;
    }
}
