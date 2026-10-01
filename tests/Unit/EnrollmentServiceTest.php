<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use DaziWeb\Oxid2Fa\Application\ChallengeRejectedException;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnrollmentService::class)]
final class EnrollmentServiceTest extends TestCase
{
    private const ADMIN = 'admin-1';

    public function testStartedSetupDoesNotCountAsActiveTwoFactor(): void
    {
        $f = new TwoFactorFixture();

        $f->setup->beginSetup(self::ADMIN);

        $this->assertNotNull($f->enrollmentService->find(self::ADMIN));
        $this->assertFalse($f->enrollmentService->isActive(self::ADMIN));
    }

    public function testResumedSetupShowsTheSameSecret(): void
    {
        $f = new TwoFactorFixture();

        $first = $f->setup->beginSetup(self::ADMIN);
        $second = $f->setup->beginSetup(self::ADMIN);

        $this->assertSame($first->secret, $second->secret);
        $this->assertStringStartsWith('otpauth://totp/', $first->provisioningUri);
    }

    public function testSetupKeyIsShownInGroupsOfFour(): void
    {
        $f = new TwoFactorFixture();

        $setup = $f->setup->beginSetup(self::ADMIN);

        $this->assertMatchesRegularExpression('/^([A-Z2-7]{4} ){7}[A-Z2-7]{4}$/', $setup->groupedSecret());
        $this->assertSame($setup->secret, str_replace(' ', '', $setup->groupedSecret()));
    }

    public function testSecretIsStoredEncrypted(): void
    {
        $f = new TwoFactorFixture();

        $setup = $f->setup->beginSetup(self::ADMIN);

        $stored = $f->enrollmentService->find(self::ADMIN)?->secretEncrypted ?? '';
        $this->assertStringNotContainsString($setup->secret, $stored);
    }

    public function testSetupIsRefusedWhenTwoFactorIsDisabledForTheShop(): void
    {
        $f = new TwoFactorFixture(Mode::Disabled);

        $this->expectException(\LogicException::class);
        $f->setup->beginSetup(self::ADMIN);
    }

    public function testRegeneratingInvalidatesTheOldCodes(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $new = $f->enrollmentService->regenerateRecoveryCodes($enrollment, $f->currentCode($enrolled['secret']));

        $this->assertCount(10, $new);
        $this->assertFalse($f->recoveryCodes->consume($enrollment->identifier, $enrolled['recoveryCodes'][0]));
        $this->assertTrue($f->recoveryCodes->consume($enrollment->identifier, $new[0]));
    }

    public function testDisablingNeedsAValidCode(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        try {
            $f->enrollmentService->disable($enrollment, '000000');
            $this->fail('Expected rejection');
        } catch (ChallengeRejectedException $rejected) {
            $this->assertSame(ChallengeResult::Failed, $rejected->result);
        }
        $this->assertTrue($f->enrollmentService->isActive(self::ADMIN));
    }

    public function testDisablingWithValidCodeRemovesTwoFactor(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $f->enrollmentService->disable($enrollment, $f->currentCode($enrolled['secret']));

        $this->assertNull($f->enrollmentService->find(self::ADMIN));
    }

    public function testResetMakesOldSecretAndRecoveryCodesUselessAndForcesNewSetup(): void
    {
        // Given an admin with 2FA under mandatory policy who lost device and recovery codes
        $f = new TwoFactorFixture(Mode::Mandatory);
        $enrolled = $f->enrollUser(self::ADMIN);

        // When a main administrator resets the account
        $f->enrollmentService->reset(self::ADMIN, 'main-admin');

        // Then the next login can only lead into a new setup; old code material does not work
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $step = $f->gate->afterPasswordVerified();
        $this->assertSame('setup', $step?->value);
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($f->currentCode($enrolled['secret'])));
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($enrolled['recoveryCodes'][0]));
        $this->assertFalse($f->session->isFullyAuthenticated());
        $newSetup = $f->flow->beginSetup();
        $this->assertNotSame($enrolled['secret'], $newSetup?->secret);
    }

    public function testResetClearsLockoutAndIsAuditedWithoutSecrets(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        for ($i = 0; $i < 5; $i++) {
            $f->throttle->reserveAttempt(self::ADMIN);
        }

        $f->enrollmentService->reset(self::ADMIN, 'cli');

        $this->assertSame(1, $f->throttle->reserveAttempt(self::ADMIN), 'budget is fresh again');
        $entry = end($f->auditEntries);
        $this->assertSame('2FA_RESET', $entry['message']);
        $this->assertSame('cli', $entry['context']['actor_id']);
        $serialized = json_encode($f->auditEntries, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($enrolled['secret'], $serialized);
        $this->assertStringNotContainsString($enrolled['recoveryCodes'][0], $serialized);
    }

    public function testFailedAndLockedAttemptsRecordWhereTheyCameFrom(): void
    {
        // Given an enrolled admin and somebody guessing codes
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        // When the attempt budget is used up and exceeded
        for ($i = 0; $i < 6; $i++) {
            $f->challenge->verify($enrollment, '000000');
        }

        // Then the failures and the lock are in the audit log, with the origin of the requests
        $failed = array_filter($f->auditEntries, static fn (array $e) => $e['message'] === '2FA_CHALLENGE_FAILED');
        $locked = array_filter($f->auditEntries, static fn (array $e) => $e['message'] === '2FA_CHALLENGE_LOCKED');
        $this->assertCount(5, $failed);
        $this->assertCount(1, $locked);
        foreach ([...$failed, ...$locked] as $entry) {
            $this->assertSame('203.0.113.7', $entry['context']['ip']);
        }
    }

    public function testASuccessfulCodeRestoresTheAttemptBudget(): void
    {
        // Given an enrolled admin who mistyped four times
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(ChallengeResult::Failed, $f->challenge->verify($enrollment, '000000'));
        }

        // When the right code is entered
        $correct = $f->currentCode($enrolled['secret']);
        $this->assertSame(ChallengeResult::Passed, $f->challenge->verify($enrollment, $correct));

        // Then the full budget is available again: five more mistakes are still only "failed", the sixth is locked
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(ChallengeResult::Failed, $f->challenge->verify($enrollment, '000000'));
        }
        $this->assertSame(ChallengeResult::Locked, $f->challenge->verify($enrollment, '000000'));
    }

    public function testRecoveryCodesAreNotRegeneratedWithAWrongCodeAndTheOldOnesKeepWorking(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        try {
            $f->enrollmentService->regenerateRecoveryCodes($enrollment, '000000');
            $this->fail('Expected the wrong code to be rejected');
        } catch (ChallengeRejectedException $rejected) {
            $this->assertSame(ChallengeResult::Failed, $rejected->result);
        }

        $this->assertTrue($f->recoveryCodes->consume($enrollment->identifier, $enrolled['recoveryCodes'][0]));
    }

    public function testRegeneratingIsRefusedForALockedAccount(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);
        for ($i = 0; $i < 5; $i++) {
            $f->challenge->verify($enrollment, '000000');
        }

        $this->expectException(ChallengeRejectedException::class);
        $f->enrollmentService->regenerateRecoveryCodes($enrollment, $f->currentCode($enrolled['secret']));
    }

    public function testNoRecoveryCodesAreIssuedForAnUnfinishedSetup(): void
    {
        $f = new TwoFactorFixture();
        $f->setup->beginSetup(self::ADMIN);

        $this->assertSame([], $f->enrollmentService->reissueRecoveryCodes(self::ADMIN));
    }

    public function testTheSecretOfAnActiveTwoFactorIsNeverHandedOutAgain(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);

        $this->expectException(\LogicException::class);
        $f->setup->beginSetup(self::ADMIN);
    }

    public function testAuditTrailCoversEnableFailureAndRecoveryUseWithoutCodes(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $f->challenge->verify($enrollment, '000000' === $f->currentCode($enrolled['secret']) ? '000001' : '000000');
        $f->challenge->verify($enrollment, $enrolled['recoveryCodes'][0]);

        $events = array_column($f->auditEntries, 'message');
        $this->assertSame(['2FA_ENABLED', '2FA_CHALLENGE_FAILED', 'RECOVERY_CODE_USED'], $events);
        $log = json_encode($f->auditEntries, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($enrolled['recoveryCodes'][0], $log);
    }

    public function testAFreshSetupHasAllRecoveryCodesAndNoWarning(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $this->assertSame(10, $f->enrollmentService->remainingRecoveryCodes($enrollment));
        $this->assertFalse($f->enrollmentService->recoveryCodesRunningLow($enrollment));
    }

    public function testWarnsOnceOnlyTwoRecoveryCodesAreLeftAndRegeneratingClearsIt(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        // Given the admin logs in with recovery codes until 3 are left: still no warning
        foreach (array_slice($enrolled['recoveryCodes'], 0, 7) as $code) {
            $this->assertSame(ChallengeResult::Passed, $f->challenge->verify($enrollment, $code));
        }
        $this->assertSame(3, $f->enrollmentService->remainingRecoveryCodes($enrollment));
        $this->assertFalse($f->enrollmentService->recoveryCodesRunningLow($enrollment));

        // When the next one is used, only 2 remain: warning
        $f->challenge->verify($enrollment, $enrolled['recoveryCodes'][7]);
        $this->assertSame(2, $f->enrollmentService->remainingRecoveryCodes($enrollment));
        $this->assertTrue($f->enrollmentService->recoveryCodesRunningLow($enrollment));

        // Then new codes (confirmed with a valid authenticator code) bring the count back and clear the warning
        $f->enrollmentService->regenerateRecoveryCodes($enrollment, $f->currentCode($enrolled['secret']));
        $this->assertSame(10, $f->enrollmentService->remainingRecoveryCodes($enrollment));
        $this->assertFalse($f->enrollmentService->recoveryCodesRunningLow($enrollment));
    }

    public function testNoWarningWhenTheShopDoesNotUseRecoveryCodes(): void
    {
        $f = new TwoFactorFixture(recoveryCodes: false);
        $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $this->assertSame(0, $f->enrollmentService->remainingRecoveryCodes($enrollment));
        $this->assertFalse($f->enrollmentService->recoveryCodesRunningLow($enrollment));
    }

    public function testRegeneratingIsOnlyAuditedWhenCodesWereActuallyIssued(): void
    {
        // Given a shop that does not use recovery codes
        $f = new TwoFactorFixture(recoveryCodes: false);
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        // When the admin asks for new codes
        $codes = $f->enrollmentService->regenerateRecoveryCodes($enrollment, $f->currentCode($enrolled['secret']));

        // Then nothing was issued, so the log must not claim it
        $this->assertSame([], $codes);
        $this->assertNotContains('RECOVERY_CODES_REGENERATED', array_column($f->auditEntries, 'message'));
    }

    public function testRegeneratingIsAuditedWhenCodesWereIssued(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $f->enrollmentService->regenerateRecoveryCodes($enrollment, $f->currentCode($enrolled['secret']));

        $this->assertContains('RECOVERY_CODES_REGENERATED', array_column($f->auditEntries, 'message'));
    }
}
