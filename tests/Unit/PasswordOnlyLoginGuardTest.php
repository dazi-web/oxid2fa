<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\PasswordOnlyLoginGuard;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordOnlyLoginGuard::class)]
final class PasswordOnlyLoginGuardTest extends TestCase
{
    private const ADMIN = 'admin-1';

    public function testAnAdminWithTwoFactorCannotSignInWithTheirPasswordAlone(): void
    {
        // Given an admin with 2FA, signing in somewhere that has no second step (for example the API)
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);

        // When the password has been accepted
        $allowed = $f->passwordOnlyGuard->isAllowed(self::ADMIN);

        // Then the sign-in is refused and the operator can see it
        $this->assertFalse($allowed);
        $this->assertSame(['2FA_ENABLED', '2FA_LOGIN_REFUSED'], array_column($f->auditEntries, 'message'));
    }

    public function testAnAdminWithoutTwoFactorMaySignInWhileItIsOptional(): void
    {
        $f = new TwoFactorFixture(Mode::Optional);

        $this->assertTrue($f->passwordOnlyGuard->isAllowed(self::ADMIN));
        $this->assertSame([], $f->auditEntries);
    }

    public function testAnAdminWithoutTwoFactorIsRefusedWhenItIsMandatory(): void
    {
        // Given mandatory 2FA: the setup only exists in the admin login, so elsewhere there is no way in
        $f = new TwoFactorFixture(Mode::Mandatory);

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testAnAccountTheOperatorRequiresTwoFactorForIsRefused(): void
    {
        $f = new TwoFactorFixture(Mode::Optional);
        $f->requirements->setRequired(self::ADMIN, true);

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testNothingIsRefusedWhileTheFeatureIsSwitchedOff(): void
    {
        $enrolled = new TwoFactorFixture();
        $enrolled->enrollUser(self::ADMIN);
        $f = new TwoFactorFixture(Mode::Disabled);
        $f->enrollments->copyFrom($enrolled->enrollments);

        $this->assertTrue($f->passwordOnlyGuard->isAllowed(self::ADMIN));
        $this->assertSame([], $f->auditEntries);
    }

    public function testAnEnrolledAdminIsRefusedAlsoWhenTheKeyIsMissingUnlessTheOperatorAllowsIt(): void
    {
        $enrolled = new TwoFactorFixture();
        $enrolled->enrollUser(self::ADMIN);

        $blocking = new TwoFactorFixture(Mode::Optional, operational: false);
        $blocking->enrollments->copyFrom($enrolled->enrollments);
        $allowing = new TwoFactorFixture(Mode::Optional, operational: false, blockWithoutKey: false);
        $allowing->enrollments->copyFrom($enrolled->enrollments);

        $this->assertTrue($allowing->passwordOnlyGuard->isAllowed(self::ADMIN));
        $this->assertFalse($blocking->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testACorrectCodeSentWithThePasswordLetsAnAdminIn(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);

        $f->submission->provide($f->currentCode($enrolled['secret']));

        $this->assertTrue($f->passwordOnlyGuard->isAllowed(self::ADMIN));
        $this->assertNotContains('2FA_LOGIN_REFUSED', array_column($f->auditEntries, 'message'));
        $this->assertNull($f->submission->take(), 'the code is used up');
    }

    public function testAWrongCodeIsRefusedAndCountsAsAFailedAttempt(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);

        $f->submission->provide('000000');

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
        $this->assertSame(1, $f->throttle->attempts(self::ADMIN));
        $this->assertSame(
            ['2FA_ENABLED', '2FA_CHALLENGE_FAILED', '2FA_LOGIN_REFUSED'],
            array_column($f->auditEntries, 'message')
        );
    }

    public function testTheSameCodeCannotBeUsedTwice(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $code = $f->currentCode($enrolled['secret']);
        $f->submission->provide($code);
        $this->assertTrue($f->passwordOnlyGuard->isAllowed(self::ADMIN));

        $f->submission->provide($code);

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testARecoveryCodeWorksOnceInsteadOfTheAuthenticatorCode(): void
    {
        $f = new TwoFactorFixture();
        $recoveryCode = $f->enrollUser(self::ADMIN)['recoveryCodes'][0];

        $f->submission->provide($recoveryCode);
        $this->assertTrue($f->passwordOnlyGuard->isAllowed(self::ADMIN));

        $f->submission->provide($recoveryCode);
        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testGuessingCodesLocksTheAccountEvenForTheCorrectCode(): void
    {
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        for ($i = 0; $i < 5; $i++) {
            $f->submission->provide('000000');
            $f->passwordOnlyGuard->isAllowed(self::ADMIN);
        }

        $f->submission->provide($f->currentCode($enrolled['secret']));

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testACodeDoesNotHelpWhereThereIsNothingToCheckItAgainst(): void
    {
        // Given mandatory 2FA and an admin who has not set it up: setup only exists in the admin login
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->submission->provide('123456');

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }

    public function testACodeIsNotAcceptedWhileTheKeyIsMissing(): void
    {
        // Given an enrolled admin and a key that has gone missing: the operator chose to block such accounts
        $enrolled = new TwoFactorFixture();
        $secret = $enrolled->enrollUser(self::ADMIN)['secret'];
        $f = new TwoFactorFixture(Mode::Optional, operational: false);
        $f->enrollments->copyFrom($enrolled->enrollments);

        $f->clock->sleep(30); // a later time step than the one spent while enrolling, so the code itself is valid
        $f->submission->provide($f->currentCode($secret));

        $this->assertFalse($f->passwordOnlyGuard->isAllowed(self::ADMIN));
    }
}
