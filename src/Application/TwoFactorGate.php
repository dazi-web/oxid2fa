<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use DaziWeb\Oxid2Fa\Domain\TwoFactorPolicy;
use Psr\Clock\ClockInterface;

/**
 * Sits between "the shop accepted the password" and "the visitor is logged in": decides whether a second
 * factor is due. If so, the shop's own authentication is withheld from the session, so every entry point that
 * relies on it (controllers, AJAX, direct URLs) rejects the visitor by itself. TwoFactorLoginFlow takes over.
 */
final readonly class TwoFactorGate
{
    public function __construct(
        private LoginSession $session,
        private TwoFactorPolicy $policy,
        private TwoFactorSettings $settings,
        private EnrollmentService $enrollments,
        private RequirementRepository $requirements,
        private AuditLog $audit,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Call right after the shop has verified the password.
     *
     * @return PendingStep|null null: nothing to do, the login is complete
     */
    public function afterPasswordVerified(): ?PendingStep
    {
        // Taken first, before anything that can fail: an error below must never leave the login in place.
        $login = $this->session->withhold();
        if ($login === null) {
            return null;
        }

        $mode = $this->settings->mode();
        if ($this->settings->isConfiguredButInoperative()) {
            $this->audit->record(AuditEvent::NotOperational, $login->userId);

            if ($this->mustBlockWithoutKey($login->userId)) {
                // The secret cannot be checked without the key: do not fall back to the password alone.
                $pending = new PendingLogin($login, PendingStep::Unavailable, $this->clock->now());
                $this->session->storePending($pending);

                return PendingStep::Unavailable;
            }
        }

        if ($mode === Mode::Disabled) {
            // No database access here: a switched-off feature must never be able to block a login.
            $this->session->restore($login);

            return null;
        }

        $step = $this->policy->stepFor(
            $mode,
            $this->enrollments->isActive($login->userId),
            $this->requirements->isRequired($login->userId)
        );

        if ($step === null) {
            $this->session->restore($login);

            return null;
        }

        $this->session->storePending(new PendingLogin($login, $step, $this->clock->now()));

        return $step;
    }

    private function mustBlockWithoutKey(string $userId): bool
    {
        return $this->settings->blocksEnrolledAccountsWithoutKey() && $this->enrollments->isActive($userId);
    }
}
