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
 * Looking after an existing second factor: lookup, recovery codes, switching off, administrative reset.
 */
final readonly class EnrollmentService
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private RecoveryCodeService $recoveryCodes,
        private ChallengeService $challenge,
        private ChallengeThrottle $throttle,
        private AuditLog $audit,
    ) {
    }

    public function find(string $userId): ?Enrollment
    {
        return $this->enrollments->find($userId);
    }

    public function isActive(string $userId): bool
    {
        return $this->find($userId)?->isActive() === true;
    }

    public function remainingRecoveryCodes(Enrollment $enrollment): int
    {
        return $this->recoveryCodes->remaining($enrollment->identifier);
    }

    /** True if recovery codes are in use and (almost) none are left, so a lost device would mean a reset. */
    public function recoveryCodesRunningLow(Enrollment $enrollment): bool
    {
        return $this->recoveryCodes->isEnabled()
            && $this->remainingRecoveryCodes($enrollment) <= RecoveryCodeService::LOW_WATERMARK;
    }

    /**
     * @return list<string>
     * @throws ChallengeRejectedException if the confirming code is wrong or the account is locked
     */
    public function regenerateRecoveryCodes(Enrollment $enrollment, string $confirmingCode): array
    {
        $this->requirePassedChallenge($enrollment, $confirmingCode);
        $codes = $this->recoveryCodes->issue($enrollment->identifier);
        $this->auditIssued($codes, $enrollment);

        return $codes;
    }

    /**
     * Replaces the recovery codes without asking for a code. Only for flows that have just verified the second
     * factor themselves (setup in progress); everything else must use regenerateRecoveryCodes().
     *
     * @return list<string>
     */
    public function reissueRecoveryCodes(string $userId): array
    {
        $enrollment = $this->find($userId);
        if ($enrollment?->isActive() !== true) {
            return [];
        }

        $codes = $this->recoveryCodes->issue($enrollment->identifier);
        $this->auditIssued($codes, $enrollment);

        return $codes;
    }

    /** @throws ChallengeRejectedException if the confirming code is wrong or the account is locked */
    public function disable(Enrollment $enrollment, string $confirmingCode): void
    {
        $this->requirePassedChallenge($enrollment, $confirmingCode);
        $this->enrollments->delete($enrollment->userId);
        $this->audit->record(AuditEvent::Disabled, $enrollment->userId);
    }

    /**
     * Administrative recovery for a user who lost device and recovery codes. Removes secret and recovery
     * codes; with a mandatory policy the next login leads into a fresh setup.
     */
    public function reset(string $userId, string $actorId): void
    {
        // Removing one's own second factor needs the step-up of disable(); a reset is for other people's accounts.
        if ($actorId === $userId) {
            throw new \DomainException('An account cannot reset its own two-factor authentication.');
        }

        $this->enrollments->delete($userId);
        $this->throttle->reset($userId);
        $this->audit->record(AuditEvent::Reset, $userId, $actorId);
    }

    /** @param list<string> $codes nothing is issued while the shop does not use recovery codes: nothing to log then */
    private function auditIssued(array $codes, Enrollment $enrollment): void
    {
        if ($codes !== []) {
            $this->audit->record(AuditEvent::RecoveryCodesRegenerated, $enrollment->userId);
        }
    }

    private function requirePassedChallenge(Enrollment $enrollment, string $code): void
    {
        $result = $this->challenge->verify($enrollment, $code);
        if ($result !== ChallengeResult::Passed) {
            throw new ChallengeRejectedException($result);
        }
    }
}
