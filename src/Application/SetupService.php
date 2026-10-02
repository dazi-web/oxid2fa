<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use Psr\Clock\ClockInterface;

/**
 * Setting up a second factor: hand out the secret, and activate it only once the user proved to have it.
 */
final readonly class SetupService
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private TotpVerifier $totp,
        private SecretCipher $cipher,
        private RecoveryCodeService $recoveryCodes,
        private TwoFactorSettings $settings,
        private UserDirectory $users,
        private ApiTokenRevocation $apiTokens,
        private AuditLog $audit,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The secret is only handed out here. An unfinished setup is resumed, so a reload of the page keeps
     * showing the QR code that is already in the authenticator app.
     */
    public function beginSetup(string $userId): SetupData
    {
        $this->assertMayEnroll();

        $enrollment = $this->enrollments->find($userId);
        if ($enrollment?->isActive()) {
            throw new \LogicException('Two-factor authentication is already active.');
        }

        $secret = $enrollment !== null ? $this->totp->secretOf($enrollment) : $this->createSecret($userId);
        $uri = $this->totp->provisioningUri($secret, $this->settings->issuer(), $this->users->accountLabel($userId));

        return new SetupData($secret, $uri);
    }

    /**
     * @return list<string>|null the recovery codes (empty if the shop does not use them), null if the code was wrong
     */
    public function confirmSetup(string $userId, string $code): ?array
    {
        $this->assertMayEnroll();

        $enrollment = $this->enrollments->find($userId);
        if ($enrollment === null || $enrollment->isActive() || !$this->totp->verify($enrollment, $code)) {
            return null;
        }
        if (!$this->enrollments->activate($enrollment->identifier, $this->clock->now())) {
            return null;
        }

        $this->apiTokens->revokeAllFor($userId);
        $this->audit->record(AuditEvent::Enabled, $userId);

        return $this->recoveryCodes->issue($enrollment->identifier);
    }

    private function createSecret(string $userId): string
    {
        $secret = $this->totp->generateSecret();
        $this->enrollments->saveSetup($userId, $this->cipher->encrypt($secret));

        return $secret;
    }

    private function assertMayEnroll(): void
    {
        if (!$this->settings->enrollmentAllowed()) {
            throw new \LogicException('Two-factor authentication is not available for this account type.');
        }
    }
}
