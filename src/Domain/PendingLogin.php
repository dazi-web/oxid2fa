<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

use DateInterval;
use DateTimeImmutable;

final readonly class PendingLogin
{
    private const LIFETIME = 'PT15M';

    public function __construct(
        public AuthenticatedLogin $login,
        public PendingStep $step,
        public DateTimeImmutable $startedAt,
    ) {
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->startedAt->add(new DateInterval(self::LIFETIME));
    }

    public function userId(): string
    {
        return $this->login->userId;
    }

    /** Moving on restarts the time limit: the user must not be penalised for the time spent on an earlier step. */
    public function atStep(PendingStep $step, DateTimeImmutable $now): self
    {
        return new self($this->login, $step, $now);
    }
}
