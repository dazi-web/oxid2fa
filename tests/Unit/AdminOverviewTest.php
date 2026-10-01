<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\AdminOverview;
use DaziWeb\Oxid2Fa\Application\AdminOverviewRow;
use DaziWeb\Oxid2Fa\Application\UserAccount;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminOverview::class)]
final class AdminOverviewTest extends TestCase
{
    public function testShowsWhoHasTwoFactorWhoStartedAndWhoIsRequired(): void
    {
        // Given three admins: one with active 2FA, one who only started, one without
        $f = new TwoFactorFixture();
        $f->users->administrators = [
            new UserAccount('a', 'anna@example.com'),
            new UserAccount('b', 'bert@example.com'),
            new UserAccount('c', 'cora@example.com'),
        ];
        $f->enrollUser('a');
        $f->setup->beginSetup('b');
        $f->overview->setRequired('c', true, 'main-admin');

        // When the overview is built
        $rows = $f->overview->rows();

        // Then
        $this->assertSame(
            [AdminOverviewRow::STATUS_ACTIVE, AdminOverviewRow::STATUS_SETUP, AdminOverviewRow::STATUS_NONE],
            array_map(static fn (AdminOverviewRow $row) => $row->status, $rows)
        );
        $this->assertSame([false, false, true], array_map(static fn (AdminOverviewRow $row) => $row->required, $rows));
        $this->assertNotNull($rows[0]->enabledAt);
        $this->assertNull($rows[1]->enabledAt);
    }

    public function testRequiringAndReleasingAnAccountIsAudited(): void
    {
        $f = new TwoFactorFixture();
        $f->users->administrators = [new UserAccount('c', 'cora@example.com')];

        $f->overview->setRequired('c', true, 'main-admin');
        $f->overview->setRequired('c', false, 'main-admin');

        $this->assertSame(['2FA_REQUIRED_SET', '2FA_REQUIRED_CLEARED'], array_column($f->auditEntries, 'message'));
        $this->assertSame('main-admin', $f->auditEntries[0]['context']['actor_id']);
        $this->assertFalse($f->requirements->isRequired('c'));
    }

    public function testShowsFailedAttemptsAndTheLockAndLetsAMainAdminReleaseIt(): void
    {
        // Given an admin who guessed wrong codes until the account is locked
        $f = new TwoFactorFixture();
        $f->users->administrators = [
            new UserAccount('a', 'anna@example.com'),
            new UserAccount('b', 'bert@example.com'),
        ];
        for ($i = 0; $i < 5; $i++) {
            $f->throttle->reserveAttempt('a');
        }
        $f->throttle->reserveAttempt('b');

        // When the overview is built
        $rows = $f->overview->rows();

        // Then the lock and the attempts are visible
        $this->assertTrue($rows[0]->isLocked());
        $this->assertSame(5, $rows[0]->failedAttempts);
        $this->assertFalse($rows[1]->isLocked());
        $this->assertSame(1, $rows[1]->failedAttempts);

        // And releasing the lock restores the budget and is audited
        $f->overview->unlock('a', 'main-admin');
        $this->assertFalse($f->overview->rows()[0]->isLocked());
        $this->assertSame(['2FA_UNLOCKED'], array_column($f->auditEntries, 'message'));
        $this->assertSame('main-admin', $f->auditEntries[0]['context']['actor_id']);
    }

    public function testOnlyBackEndAccountsCanBeFlagged(): void
    {
        $f = new TwoFactorFixture();

        $f->overview->setRequired('not-an-admin', true, 'main-admin');

        $this->assertFalse($f->requirements->isRequired('not-an-admin'));
        $this->assertSame([], $f->auditEntries);
    }

    public function testOnlyBackEndAccountsCanBeUnlocked(): void
    {
        $f = new TwoFactorFixture();
        for ($i = 0; $i < 5; $i++) {
            $f->throttle->reserveAttempt('not-an-admin');
        }

        $f->overview->unlock('not-an-admin', 'main-admin');

        $this->assertSame(5, $f->throttle->attempts('not-an-admin'));
        $this->assertSame([], $f->auditEntries);
    }

    public function testAnAccountCannotResetItsOwnTwoFactorWithoutACode(): void
    {
        $f = new TwoFactorFixture();
        $f->enrollUser('a');

        try {
            $f->enrollmentService->reset('a', 'a');
            $this->fail('Expected the self reset to be refused');
        } catch (\DomainException) {
        }

        $this->assertTrue($f->enrollmentService->isActive('a'));
    }
}
