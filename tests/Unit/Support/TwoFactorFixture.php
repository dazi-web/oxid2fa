<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use OTPHP\TOTP;
use DaziWeb\Oxid2Fa\Application\AccountCleanup;
use DaziWeb\Oxid2Fa\Application\AdminOverview;
use DaziWeb\Oxid2Fa\Application\AuditLog;
use DaziWeb\Oxid2Fa\Application\ChallengeService;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use DaziWeb\Oxid2Fa\Application\PasswordOnlyLoginGuard;
use DaziWeb\Oxid2Fa\Application\RecoveryCodeService;
use DaziWeb\Oxid2Fa\Application\SecondFactorDecision;
use DaziWeb\Oxid2Fa\Application\TotpVerifier;
use DaziWeb\Oxid2Fa\Application\SetupService;
use DaziWeb\Oxid2Fa\Application\TwoFactorGate;
use DaziWeb\Oxid2Fa\Application\TwoFactorLoginFlow;
use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\TwoFactorPolicy;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use Psr\Log\AbstractLogger;
use Symfony\Component\Clock\MockClock;

/**
 * Wires the real services against in-memory infrastructure.
 */
final class TwoFactorFixture
{
    public MockClock $clock;
    public FakeLoginSession $session;
    public FakeUserDirectory $users;
    public InMemoryEnrollmentRepository $enrollments;
    public InMemoryChallengeThrottle $throttle;
    public RecoveryCodeService $recoveryCodes;
    public EnrollmentService $enrollmentService;
    public ChallengeService $challenge;
    public SecondFactorDecision $decision;
    public PasswordOnlyLoginGuard $passwordOnlyGuard;
    public TwoFactorGate $gate;
    public TwoFactorLoginFlow $flow;
    public SetupService $setup;
    public InMemoryRequirementRepository $requirements;
    public AdminOverview $overview;
    public AccountCleanup $accountCleanup;
    public SecretCipher $cipher;
    public TotpVerifier $totp;
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $auditEntries = [];

    public function __construct(
        Mode $adminMode = Mode::Optional,
        bool $recoveryCodes = true,
        bool $operational = true,
        bool $blockWithoutKey = true,
    ) {
        $this->clock = new MockClock('2026-10-01 12:00:00');
        $this->session = new FakeLoginSession();
        $this->users = new FakeUserDirectory();
        $this->enrollments = new InMemoryEnrollmentRepository();
        $this->throttle = new InMemoryChallengeThrottle();
        $this->requirements = new InMemoryRequirementRepository();
        $key = new EncryptionKey(str_repeat('k', 32));
        $this->cipher = new SecretCipher($key);
        $settings = new TwoFactorSettings($adminMode, $operational, $recoveryCodes, 10, 'Testshop', $blockWithoutKey);
        $this->recoveryCodes = new RecoveryCodeService(
            new InMemoryRecoveryCodeRepository(),
            $key,
            $settings,
            $this->clock
        );
        $audit = new AuditLog($this->collectingLogger(), new FakeRequestContext());
        $this->totp = new TotpVerifier($this->clock, $this->cipher, $this->enrollments);

        $this->challenge = new ChallengeService($this->totp, $this->recoveryCodes, $this->throttle, $audit);
        $this->setup = new SetupService(
            $this->enrollments,
            $this->totp,
            $this->cipher,
            $this->recoveryCodes,
            $settings,
            $this->users,
            $audit,
            $this->clock
        );
        $this->enrollmentService = new EnrollmentService(
            $this->enrollments,
            $this->recoveryCodes,
            $this->challenge,
            $this->throttle,
            $audit
        );
        $this->decision = new SecondFactorDecision(
            new TwoFactorPolicy(),
            $settings,
            $this->enrollmentService,
            $this->requirements
        );
        $this->gate = new TwoFactorGate($this->session, $settings, $this->decision, $audit, $this->clock);
        $this->passwordOnlyGuard = new PasswordOnlyLoginGuard($this->decision, $audit);
        $this->flow = new TwoFactorLoginFlow(
            $this->session,
            $this->challenge,
            $this->enrollmentService,
            $this->setup,
            $this->users,
            $this->clock
        );
        $this->overview = new AdminOverview(
            $this->users,
            $this->enrollments,
            $this->requirements,
            $this->throttle,
            $audit
        );
        $this->accountCleanup = new AccountCleanup($this->enrollments, $this->requirements, $this->throttle);
    }

    /**
     * @return array{secret: string, recoveryCodes: list<string>}
     */
    public function enrollUser(string $userId): array
    {
        $setup = $this->setup->beginSetup($userId);
        $codes = $this->setup->confirmSetup($userId, $this->currentCode($setup->secret));
        $this->clock->sleep(30); // the confirming code is spent; later logins use the next step

        return ['secret' => $setup->secret, 'recoveryCodes' => $codes ?? []];
    }

    public function currentCode(string $secret): string
    {
        return TOTP::createFromSecret($secret, $this->clock)->now();
    }

    public function codeAtOffset(string $secret, int $seconds): string
    {
        return TOTP::createFromSecret($secret, $this->clock)->at($this->clock->now()->getTimestamp() + $seconds);
    }

    private function collectingLogger(): AbstractLogger
    {
        $fixture = $this;

        return new class ($fixture) extends AbstractLogger {
            public function __construct(private TwoFactorFixture $fixture)
            {
            }

            public function log($level, $message, array $context = [])
            {
                $this->fixture->auditEntries[] = ['message' => (string)$message, 'context' => $context];
            }
        };
    }
}
