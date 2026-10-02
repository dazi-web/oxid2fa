<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingStep;

/**
 * Whether an administrator whose password was just accepted still has to pass a second factor, and which step that
 * is. The admin login (TwoFactorGate) and other ways to sign in with a password, such as an API, ask the same thing.
 */
final readonly class SecondFactorDecision
{
    public function __construct(
        private TwoFactorSettings $settings,
        private EnrollmentService $enrollments,
        private RequirementRepository $requirements,
    ) {
    }

    /** @return PendingStep|null null: the password is enough */
    public function stepFor(string $userId): ?PendingStep
    {
        $enrolled = fn (): bool => $this->enrollments->isActive($userId);

        if (
            $this->settings->isConfiguredButInoperative() && $this->settings->blocksEnrolledAccountsWithoutKey()
            && $enrolled()
        ) {
            // The secret cannot be checked without the key: do not fall back to the password alone.
            return PendingStep::Unavailable;
        }

        $mode = $this->settings->mode();
        if ($mode === Mode::Disabled) {
            // No database access here: a switched-off feature must never be able to block a login.
            return null;
        }

        // An existing enrolment is always enforced, even if the shop owner later switches the mode back to "optional":
        // otherwise a stolen password would be enough to bypass the second factor.
        if ($enrolled()) {
            return PendingStep::VerifyCode;
        }

        // An operator can require 2FA for single accounts even while it is optional for everybody else.
        $required = $mode === Mode::Mandatory || $this->requirements->isRequired($userId);

        return $required ? PendingStep::SetupRequired : null;
    }
}
