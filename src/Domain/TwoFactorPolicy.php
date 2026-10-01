<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

final class TwoFactorPolicy
{
    /**
     * The step a login has to go through after the password check; null if the login is complete.
     *
     * An existing enrolment is always enforced, even if the shop owner later switches the mode back to
     * "optional": otherwise a stolen password would be enough to bypass the second factor.
     */
    public function stepFor(Mode $mode, bool $hasActiveEnrollment, bool $requiredForThisUser = false): ?PendingStep
    {
        if ($mode === Mode::Disabled) {
            return null;
        }

        if ($hasActiveEnrollment) {
            return PendingStep::VerifyCode;
        }

        // An operator can require 2FA for single accounts even while it is optional for everybody else.
        return $mode === Mode::Mandatory || $requiredForThisUser ? PendingStep::SetupRequired : null;
    }
}
