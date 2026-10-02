<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\AdminOverview;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use DaziWeb\Oxid2Fa\Application\SetupService;
use DaziWeb\Oxid2Fa\Application\UserAccount;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** An API session that was started with the password alone must end when the account gets a second factor. */
#[CoversClass(SetupService::class)]
#[CoversClass(AdminOverview::class)]
#[CoversClass(EnrollmentService::class)]
final class ApiTokenRevocationTest extends TestCase
{
    private const ADMIN = 'admin-1';

    public function testEnablingTwoFactorEndsTheApiSessionsOfThatAccount(): void
    {
        $f = new TwoFactorFixture();

        $f->enrollUser(self::ADMIN);

        $this->assertSame([self::ADMIN], $f->apiTokens->revoked);
    }

    public function testAWrongCodeDuringSetupEndsNothing(): void
    {
        $f = new TwoFactorFixture();
        $f->setup->beginSetup(self::ADMIN);

        $f->setup->confirmSetup(self::ADMIN, '000000');

        $this->assertSame([], $f->apiTokens->revoked);
    }

    public function testRequiringTwoFactorForAnAccountEndsItsApiSessionsButReleasingDoesNot(): void
    {
        $f = new TwoFactorFixture();
        $f->users->administrators = [new UserAccount(self::ADMIN, 'anna@example.com')];

        $f->overview->setRequired(self::ADMIN, true, 'main-admin');
        $this->assertSame([self::ADMIN], $f->apiTokens->revoked);

        $f->overview->setRequired(self::ADMIN, false, 'main-admin');
        $this->assertSame([self::ADMIN], $f->apiTokens->revoked, 'releasing is no reason to end sessions');
    }

    public function testRequiringTwoFactorForSomebodyWhoIsNoAdministratorDoesNothing(): void
    {
        $f = new TwoFactorFixture();

        $f->overview->setRequired('a-customer', true, 'main-admin');

        $this->assertSame([], $f->apiTokens->revoked);
    }

    public function testAResetEndsTheApiSessionsOfThatAccount(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->apiTokens->revoked = [];

        $f->enrollmentService->reset(self::ADMIN, 'main-admin');

        $this->assertSame([self::ADMIN], $f->apiTokens->revoked);
    }

    public function testAResetThatIsRefusedEndsNothing(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser(self::ADMIN);
        $f->apiTokens->revoked = [];

        try {
            $f->enrollmentService->reset(self::ADMIN, self::ADMIN);
            $this->fail('Expected the reset of one\'s own account to be refused');
        } catch (\DomainException) {
            $this->assertSame([], $f->apiTokens->revoked);
        }
    }

    public function testSwitchingTwoFactorOffDoesNotEndApiSessions(): void
    {
        $f = new TwoFactorFixture();
        $secret = $f->enrollUser(self::ADMIN)['secret'];
        $f->apiTokens->revoked = [];
        $enrollment = $f->enrollmentService->find(self::ADMIN);
        $this->assertNotNull($enrollment);

        $f->enrollmentService->disable($enrollment, $f->currentCode($secret));

        $this->assertSame([], $f->apiTokens->revoked);
    }
}
