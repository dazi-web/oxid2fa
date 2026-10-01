<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

interface ChallengeThrottle
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Counts an attempt for the account *before* the code is checked. The increment is atomic, so parallel
     * requests cannot share one attempt: of any number of simultaneous requests at most MAX_ATTEMPTS get a
     * number within the budget.
     *
     * @return int the number of this attempt; above MAX_ATTEMPTS means the account is locked
     */
    public function reserveAttempt(string $userId): int;

    /** Attempts counted in the current window, without counting a new one. */
    public function attempts(string $userId): int;

    /** Forgets all attempts, after a successful challenge or an administrative reset. */
    public function reset(string $userId): void;
}
