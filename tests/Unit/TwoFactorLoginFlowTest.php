<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\TwoFactorLoginFlow;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A waiting login may only do what its current step allows. Every other call must neither complete the
 * login nor change anything: otherwise a password alone, or a skipped setup, would be enough.
 */
#[CoversClass(TwoFactorLoginFlow::class)]
final class TwoFactorLoginFlowTest extends TestCase
{
    private const ADMIN = 'admin-1';

    public function testPasswordOnlyLoginCannotBeCompletedByAcknowledgingRecoveryCodes(): void
    {
        // Given an admin with 2FA who has passed the password check only
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        // When the visitor skips the code and calls the last step of the setup flow
        $accepted = $f->flow->acknowledgeRecoveryCodes();

        // Then nothing happens
        $this->assertFalse($accepted);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertSame(PendingStep::VerifyCode, $f->flow->pending()?->step);
    }

    public function testMandatorySetupCannotBeSkippedByAcknowledging(): void
    {
        // Given mandatory 2FA and an admin who still has to set it up
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        // When the admin jumps straight to "continue"
        $accepted = $f->flow->acknowledgeRecoveryCodes();

        // Then the login stays withheld and no second factor exists
        $this->assertFalse($accepted);
        $this->assertFalse($f->session->isFullyAuthenticated());
        $this->assertFalse($f->enrollmentService->isActive(self::ADMIN));
        $this->assertSame(PendingStep::SetupRequired, $f->flow->pending()?->step);
    }

    public function testRecoveryCodesCannotBeReissuedBeforeTheSecondFactorWasProven(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        $this->assertNull($f->flow->reissueRecoveryCodes());
    }

    public function testNoCodeIsAcceptedWhileTheRecoveryCodesAreStillToBeAcknowledged(): void
    {
        // Given a finished setup: the second factor is proven, only the acknowledgement is open
        $f = new TwoFactorFixture(Mode::Mandatory);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();
        $setup = $f->flow->beginSetup();
        $f->flow->confirmSetup($f->currentCode($setup?->secret ?? ''));
        $f->clock->sleep(30);

        // When a fresh, valid code is submitted in this step
        $result = $f->flow->verifyCode($f->currentCode($setup?->secret ?? ''));

        // Then it is not a way to complete the login: the step is acknowledge, not verify
        $this->assertSame(ChallengeResult::Failed, $result);
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testSetupCannotBeConfirmedForAnAccountThatAlreadyHasTwoFactor(): void
    {
        // Given an enrolled admin waiting for the code
        $f = new TwoFactorFixture();
        $enrolled = $f->enrollUser(self::ADMIN);
        $f->session->simulateShopPasswordLogin(self::ADMIN);
        $f->gate->afterPasswordVerified();

        // When the setup call is used with a valid code
        $codes = $f->flow->confirmSetup($f->currentCode($enrolled['secret']));

        // Then it is refused and does not log in
        $this->assertNull($codes);
        $this->assertFalse($f->session->isFullyAuthenticated());
    }

    public function testNothingWorksWithoutAWaitingLogin(): void
    {
        $f = new TwoFactorFixture(Mode::Mandatory);

        $this->assertNull($f->flow->pending());
        $this->assertSame(ChallengeResult::Failed, $f->flow->verifyCode('123456'));
        $this->assertNull($f->flow->beginSetup());
        $this->assertNull($f->flow->confirmSetup('123456'));
        $this->assertNull($f->flow->reissueRecoveryCodes());
        $this->assertFalse($f->flow->acknowledgeRecoveryCodes());
        $this->assertFalse($f->session->isFullyAuthenticated());
    }
}
