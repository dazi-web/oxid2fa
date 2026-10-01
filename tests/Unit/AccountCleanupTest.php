<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\AccountCleanup;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountCleanup::class)]
final class AccountCleanupTest extends TestCase
{
    public function testADeletedAccountLeavesNoSecretRequirementOrLockBehind(): void
    {
        // Given an admin with 2FA, a requirement flag and a lock, and another admin with 2FA
        $f = new TwoFactorFixture();
        $f->enrollUser('gone');
        $f->enrollUser('stays');
        $f->requirements->setRequired('gone', true);
        $f->requirements->setRequired('stays', true);
        for ($i = 0; $i < 5; $i++) {
            $f->throttle->reserveAttempt('gone');
        }

        // When the account is deleted
        $f->accountCleanup->forget('gone');

        // Then nothing about it remains
        $this->assertNull($f->enrollmentService->find('gone'));
        $this->assertFalse($f->requirements->isRequired('gone'));
        $this->assertSame(0, $f->throttle->attempts('gone'));

        // And the other account is untouched
        $this->assertTrue($f->enrollmentService->isActive('stays'));
        $this->assertTrue($f->requirements->isRequired('stays'));
    }

    public function testForgettingAnAccountWithoutTwoFactorIsHarmless(): void
    {
        $f = new TwoFactorFixture();

        $f->accountCleanup->forget('never-enrolled');

        $this->assertNull($f->enrollmentService->find('never-enrolled'));
    }
}
