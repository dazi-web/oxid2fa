<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use Psr\Clock\ClockInterface;

/**
 * The steps of a login that has passed the password check but is still waiting for the second factor:
 * verify a code, set up a second factor, confirm the recovery codes. Only the last step of each path hands the
 * shop's authentication back and rotates the session.
 */
final readonly class TwoFactorLoginFlow
{
    public function __construct(
        private LoginSession $session,
        private ChallengeService $challenge,
        private EnrollmentService $enrollments,
        private SetupService $setup,
        private UserDirectory $users,
        private ClockInterface $clock,
    ) {
    }

    public function pending(): ?PendingLogin
    {
        $pending = $this->session->pending();
        if ($pending === null) {
            return null;
        }

        if ($pending->isExpiredAt($this->clock->now()) || !$this->users->isActive($pending->userId())) {
            $this->session->clearPending();

            return null;
        }

        return $pending;
    }

    public function verifyCode(string $input): ChallengeResult
    {
        $pending = $this->pending();
        if ($pending?->step !== PendingStep::VerifyCode) {
            return ChallengeResult::Failed;
        }

        $enrollment = $this->enrollments->find($pending->userId());
        if ($enrollment?->isActive() !== true) {
            $this->session->clearPending();

            return ChallengeResult::Failed;
        }

        $result = $this->challenge->verify($enrollment, $input);
        if ($result === ChallengeResult::Passed) {
            $this->completeLogin($pending);
        }

        return $result;
    }

    public function beginSetup(): ?SetupData
    {
        $pending = $this->pending();
        if ($pending?->step !== PendingStep::SetupRequired) {
            return null;
        }

        return $this->setup->beginSetup($pending->userId());
    }

    /**
     * @return list<string>|null recovery codes to show once, null if the code was wrong
     */
    public function confirmSetup(string $code): ?array
    {
        $pending = $this->pending();
        if ($pending?->step !== PendingStep::SetupRequired) {
            return null;
        }

        $codes = $this->setup->confirmSetup($pending->userId(), $code);
        if ($codes === null) {
            return null;
        }

        if ($codes === []) {
            $this->completeLogin($pending);
        } else {
            $this->session->storePending($pending->atStep(PendingStep::AcknowledgeRecoveryCodes, $this->clock->now()));
        }

        return $codes;
    }

    /**
     * The codes are shown once, in the response to the confirmed setup, and are never kept in clear text. If the
     * page was reloaded or the codes got lost, the user may replace them while the setup is still open.
     *
     * @return list<string>|null
     */
    public function reissueRecoveryCodes(): ?array
    {
        $pending = $this->pending();
        if ($pending?->step !== PendingStep::AcknowledgeRecoveryCodes) {
            return null;
        }

        $this->session->storePending($pending->atStep(PendingStep::AcknowledgeRecoveryCodes, $this->clock->now()));

        return $this->enrollments->reissueRecoveryCodes($pending->userId());
    }

    public function acknowledgeRecoveryCodes(): bool
    {
        $pending = $this->pending();
        if ($pending?->step !== PendingStep::AcknowledgeRecoveryCodes) {
            return false;
        }

        $this->completeLogin($pending);

        return true;
    }

    public function abandon(): void
    {
        $this->session->abandon();
    }

    private function completeLogin(PendingLogin $pending): void
    {
        $this->session->restore($pending->login);
        $this->session->rotateSessionId();
        $this->session->clearPending();
    }
}
