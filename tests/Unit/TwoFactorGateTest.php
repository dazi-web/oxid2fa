<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use DaziWeb\Oxid2Fa\Application\TwoFactorGate;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoFactorGate::class)]
final class TwoFactorGateTest extends TestCase
{
    private const ADMIN = 'admin-1';

    public function testFailedPasswordLoginLeavesNothingBehind(): void
    {
        // Given the shop rejected the password, so no login was written to the session
        $f = new TwoFactorFixture();

        // When the gate runs
        $step = $f->gate->afterPasswordVerified();

        // Then nothing happens
        $this->assertNull($step);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertNull($f->flow->pending());
    }

    public function testPasswordLoginWithoutNeedForSecondFactorStaysUntouched(): void
    {
        // Given optional 2FA and an admin who never set it up
        $f = new TwoFactorFixture(Mode::Optional);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When
        $step = $f->gate->afterPasswordVerified();

        // Then the admin is logged in as before, without a session rotation
        $this->assertNull($step);
        $this->assertTrue($f->session->isFullyAuthenticated());
        $this->assertSame(0, $f->session->rotations);
    }

    public function testDisabledModeIgnoresExistingEnrolment(): void
    {
        $f = new TwoFactorFixture(Mode::Optional);
        $f->enrollUser(self::ADMIN);
        $disabled = new TwoFactorFixture(Mode::Disabled);
        $disabled->session->simulateShopPasswordLogin(self::ADMIN);

        $this->assertNull($disabled->gate->afterPasswordVerified());
        $this->assertTrue($disabled->session->isFullyAuthenticated());
    }

    public function testSwitchedOffFeatureNeverTouchesTheDatabase(): void
    {
        // Given 2FA is disabled and the 2FA tables are unavailable (e.g. migration not run yet)
        $f = new TwoFactorFixture(Mode::Disabled);
        $f->enrollments->unavailable = true;
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When / Then the login goes through
        $this->assertNull($f->gate->afterPasswordVerified());
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testWithoutEncryptionKeyAccountsWithoutTwoFactorCanStillLogIn(): void
    {
        // Given mandatory 2FA but no key: nobody can enrol, so there is nothing to protect or to wait for
        $f = new TwoFactorFixture(Mode::Mandatory, operational: false);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        $this->assertNull($f->gate->afterPasswordVerified());
        $this->assertTrue($f->session->isFullyAuthenticated());
        $this->assertSame(['2FA_NOT_OPERATIONAL'], array_column($f->auditEntries, 'message'));
    }

    public function testWithoutEncryptionKeyAccountWithActiveTwoFactorIsBlockedByDefault(): void
    {
        // Given an enrolled admin and a key that has gone missing
        $enrolled = new TwoFactorFixture();
        $enrolled->enrollUser(self::ADMIN);
        $f = new TwoFactorFixture(Mode::Optional, operational: false);
        $f->enrollments->copyFrom($enrolled->enrollments);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When the password has been verified
        $step = $f->gate->afterPasswordVerified();

        // Then the password alone is not enough, and no code can be submitted
        $this->assertSame(PendingStep::Unavailable, $step);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode('123456'));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testWithoutEncryptionKeyPasswordOnlyLoginCanBeAllowedByTheOperator(): void
    {
        $enrolled = new TwoFactorFixture();
        $enrolled->enrollUser(self::ADMIN);
        $f = new TwoFactorFixture(Mode::Optional, operational: false, blockWithoutKey: false);
        $f->enrollments->copyFrom($enrolled->enrollments);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        $this->assertNull($f->gate->afterPasswordVerified());
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testDisabledFeatureWithoutKeyNeverBlocks(): void
    {
        $f = new TwoFactorFixture(Mode::Disabled, operational: false);
        $f->enrollments->unavailable = true;
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        $this->assertNull($f->gate->afterPasswordVerified());
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testAdminWithTwoFactorIsNotAuthenticatedBeforeTheChallengeIsPassed(): void
    {
        // Given an admin with active TOTP
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);

        // When the password has been verified
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $step = $f->gate->afterPasswordVerified();

        // Then no protected area sees a login, but a challenge is waiting
        $this->assertSame(PendingStep::VerifyCode, $step);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertSame(PendingStep::VerifyCode, $f->flow->pending()?->step);
    }

    public function testValidTotpCodeCompletesLoginAndRotatesTheSession(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $result = $f->flow->verifyCode($f->currentCode($enrolled['secret']));

        $this->assertSame(ChallengeResult::Passed, $result);
        $this->assertTrue($f->session->isFullyAuthenticated());
        $this->assertSame(self::ADMIN, $f->session->authenticated?->userId);
        $this->assertSame(1, $f->session->rotations);
        $this->assertNull($f->flow->pending());
    }

    public function testInvalidCodeKeepsTheLoginWithheld(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $wrong = $f->currentCode($enrolled['secret']) === '123456' ? '654321' : '123456';

        $result = $f->flow->verifyCode($wrong);

        $this->assertSame(ChallengeResult::Failed, $result);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertNotNull($f->flow->pending());
    }

    public function testRecoveryCodeCompletesLoginExactlyOnce(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $recovery = $enrolled['recoveryCodes'][0];

        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $this->assertSame(ChallengeResult::Passed, $f->flow->verifyCode($recovery));
        $this->assertTrue($f->session->isFullyAuthenticated());

        $f->session->authenticated = null;
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($recovery));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testLogoutDuringChallengeDropsEverything(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $f->flow->abandon();

        $this->assertTrue($f->session->destroyed);
        $this->assertNull($f->flow->pending());
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testAbandonedChallengeExpires(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $f->clock->sleep(16 * 60);

        $this->assertNull($f->flow->pending());
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($f->currentCode($enrolled['secret'])));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testUserDeactivatedDuringChallengeCannotFinishLogin(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $f->users->inactive[self::ADMIN] = true;

        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($f->currentCode($enrolled['secret'])));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testRepeatedWrongCodesLockTheChallengeEvenForTheCorrectCode(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        for ($i = 0; $i < 5; $i++) {
            $f->flow->verifyCode('000000' === $f->currentCode($enrolled['secret']) ? '000001' : '000000');
        }

        $this->assertSame(ChallengeResult::Locked, $f->flow->verifyCode($f->currentCode($enrolled['secret'])));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testLockIsAuditedOnceNoMatterHowOftenTheLockedAccountIsHit(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        for ($i = 0; $i < 20; $i++) {
            $f->flow->verifyCode('000000');
        }

        $events = array_count_values(array_column($f->auditEntries, 'message'));
        $this->assertSame(5, $events['2FA_CHALLENGE_FAILED']);
        $this->assertSame(1, $events['2FA_CHALLENGE_LOCKED']);
    }

    public function testReplayedTotpCodeIsRejected(): void
    {
        // Given a code that was accepted for a login
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $code = $f->currentCode($enrolled['secret']);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $this->assertSame(ChallengeResult::Passed, $f->flow->verifyCode($code));

        // When somebody who watched it uses it for a new login in the same time window
        $f->session->authenticated = null;
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        // Then it is not accepted again
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode($code));
    }

    public function testAccountFlaggedByTheOperatorMustSetUpEvenWhenTwoFactorIsOptional(): void
    {
        // Given optional 2FA, and one admin whom a main administrator flagged
        $f = new TwoFactorFixture(Mode::Optional);
        $f->requirements->setRequired(self::ADMIN, true);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When the password has been verified
        $step = $f->gate->afterPasswordVerified();

        // Then this admin is led into the setup, while others are unaffected
        $this->assertSame(PendingStep::SetupRequired, $step);
        $this->assertFalse($f->session->isFullyAuthenticated());

        $f->session->authenticated = null;
        $f->session->simulateShopPasswordLogin('other-admin');
        $this->assertNull($f->gate->afterPasswordVerified());
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testMandatoryModeLeadsAdminWithoutEnrolmentOnlyIntoSetup(): void
    {
        // Given mandatory 2FA and an admin without 2FA
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When the password has been verified
        $step = $f->gate->afterPasswordVerified();

        // Then the admin is not logged in and the only available step is the setup
        $this->assertSame(PendingStep::SetupRequired, $step);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode('123456'), 'no code login without enrolment');
        $this->assertNull($f->flow->confirmSetup('000000'));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testMandatorySetupEndsInFullLoginAfterRecoveryCodesWereAcknowledged(): void
    {
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $setup = $f->flow->beginSetup();
        $this->assertNotNull($setup);
        $codes = $f->flow->confirmSetup($f->currentCode($setup->secret));

        $this->assertCount(10, $codes ?? []);
        $this->assertFalse($f->session->isFullyAuthenticated(), 'still locked until the codes are acknowledged');
        $this->assertTrue($f->flow->acknowledgeRecoveryCodes());
        $this->assertTrue($f->session->isFullyAuthenticated());
        $this->assertSame(1, $f->session->rotations);
    }

    public function testLostRecoveryCodesCanBeReplacedBeforeTheLoginIsCompleted(): void
    {
        // Given a finished setup whose recovery codes were not saved (page reloaded)
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $setup = $f->flow->beginSetup();
        $shown = $f->flow->confirmSetup($f->currentCode($setup?->secret ?? ''));
        $f->clock->sleep(14 * 60);

        // When the user asks for new codes
        $reissued = $f->flow->reissueRecoveryCodes();

        // Then the old codes are void, the new ones work, and the time limit started over
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);
        $this->assertCount(10, $reissued ?? []);
        $this->assertFalse($f->recoveryCodes->consume($enrollment->identifier, $shown[0] ?? ''));
        $this->assertTrue($f->recoveryCodes->consume($enrollment->identifier, $reissued[0] ?? ''));
        $f->clock->sleep(14 * 60);
        $this->assertTrue($f->flow->acknowledgeRecoveryCodes());
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testRecoveryCodesCannotBeReplacedWithoutPassingTheSecondFactor(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $this->assertNull($f->flow->reissueRecoveryCodes());
    }

    public function testSetupWithoutRecoveryCodesFinishesRightAway(): void
    {
        $f = new TwoFactorFixture(Mode::Mandatory, recoveryCodes: false);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $setup = $f->flow->beginSetup();

        $codes = $f->flow->confirmSetup($f->currentCode($setup?->secret ?? ''));

        $this->assertSame([], $codes);
        $this->assertTrue($f->session->isFullyAuthenticated());
    }

    public function testWrongCodeDoesNotFinishSetup(): void
    {
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $setup = $f->flow->beginSetup();
        $wrong = $f->currentCode($setup?->secret ?? '') === '000000' ? '000001' : '000000';

        $this->assertNull($f->flow->confirmSetup($wrong));
        $this->assertFalse($f->enrollmentService->isActive(self::ADMIN));
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testFailureWhileDecidingDoesNotLeaveTheLoginInPlace(): void
    {
        // Given the enrolment lookup fails right after the shop wrote the login into the session
        $f = new TwoFactorFixture();
        $f->enrollments->unavailable = true;
        $f->session->simulateShopPasswordLogin(self::ADMIN);

        // When the gate runs
        try {
            $f->gate->afterPasswordVerified();
            $this->fail('Expected the failure to surface');
        } catch (\RuntimeException) {
        }

        // Then the visitor is not logged in (fail closed)
        $this->assertFalse($f->session->isFullyAuthenticated());
    }
}
