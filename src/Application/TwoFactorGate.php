<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
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
        private TwoFactorSettings $settings,
        private SecondFactorDecision $decision,
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

        if ($this->settings->isConfiguredButInoperative()) {
            $this->audit->record(AuditEvent::NotOperational, $login->userId);
        }

        $step = $this->decision->stepFor($login->userId);
        if ($step === null) {
            $this->session->restore($login);

            return null;
        }

        $this->session->storePending(new PendingLogin($login, $step, $this->clock->now()));

        return $step;
    }
}
