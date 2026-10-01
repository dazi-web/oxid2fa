<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;

/**
 * For every way of signing in with a password that has no place for a second factor (shop front end, GraphQL API,
 * other modules): an administrator who owes a second factor is refused instead of getting in with the password alone.
 * The admin login itself is not covered here; TwoFactorGate holds that login back and asks for the code.
 */
final readonly class PasswordOnlyLoginGuard
{
    public function __construct(
        private SecondFactorDecision $decision,
        private AuditLog $audit,
    ) {
    }

    /** @throws ChallengeRequired */
    public function assertAllowed(string $userId): void
    {
        if ($this->decision->stepFor($userId) === null) {
            return;
        }

        $this->audit->record(AuditEvent::LoginRefused, $userId);

        throw new ChallengeRequired('A second factor is required for this account.');
    }
}
