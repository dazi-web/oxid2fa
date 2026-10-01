<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\PendingStep;

/**
 * For every way of signing in with a password that has no second page (shop front end, GraphQL API, other modules):
 * an administrator who owes a second factor is refused instead of getting in with the password alone, unless the
 * client sent a correct code along (SecondFactorSubmission). The admin login itself is not covered here; TwoFactorGate
 * holds that login back and asks for the code.
 */
final readonly class PasswordOnlyLoginGuard
{
    public function __construct(
        private SecondFactorDecision $decision,
        private SecondFactorSubmission $submission,
        private EnrollmentService $enrollments,
        private ChallengeService $challenge,
        private AuditLog $audit,
    ) {
    }

    /** @throws ChallengeRequired */
    public function assertAllowed(string $userId): void
    {
        $step = $this->decision->stepFor($userId);
        if ($step === null || ($step === PendingStep::VerifyCode && $this->codeIsCorrect($userId))) {
            return;
        }

        $this->audit->record(AuditEvent::LoginRefused, $userId);

        throw new ChallengeRequired('A second factor is required for this account.');
    }

    private function codeIsCorrect(string $userId): bool
    {
        $code = $this->submission->take();
        $enrollment = $this->enrollments->find($userId);
        if ($code === null || $enrollment?->isActive() !== true) {
            return false;
        }

        return $this->challenge->verify($enrollment, $code) === ChallengeResult::Passed;
    }
}
