<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

/**
 * Checks what the user typed against an active enrolment: an authenticator code or a recovery code.
 * Both count against the same attempt budget.
 */
final readonly class ChallengeService
{
    public function __construct(
        private TotpVerifier $totp,
        private RecoveryCodeService $recoveryCodes,
        private ChallengeThrottle $throttle,
        private AuditLog $audit,
    ) {
    }

    public function verify(Enrollment $enrollment, string $input): ChallengeResult
    {
        $userId = $enrollment->userId;

        // Counted before checking, so concurrent guesses cannot all slip past a check-then-record gap.
        $attempt = $this->throttle->reserveAttempt($userId);
        if ($attempt > ChallengeThrottle::MAX_ATTEMPTS) {
            if ($attempt === ChallengeThrottle::MAX_ATTEMPTS + 1) {
                $this->audit->record(AuditEvent::ChallengeLocked, $userId);
            }

            return ChallengeResult::Locked;
        }

        if ($this->isAccepted($enrollment, $input)) {
            $this->throttle->reset($userId);

            return ChallengeResult::Passed;
        }

        $this->audit->record(AuditEvent::ChallengeFailed, $userId);

        return ChallengeResult::Failed;
    }

    private function isAccepted(Enrollment $enrollment, string $input): bool
    {
        if ($this->looksLikeTotpCode($input)) {
            return $this->totp->verify($enrollment, $input);
        }

        if (!$this->recoveryCodes->consume($enrollment->identifier, $input)) {
            return false;
        }

        $this->audit->record(AuditEvent::RecoveryCodeUsed, $enrollment->userId);

        return true;
    }

    private function looksLikeTotpCode(string $input): bool
    {
        return preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $input) ?? '') === 1;
    }
}
