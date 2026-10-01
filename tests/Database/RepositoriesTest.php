<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Database;

use DaziWeb\Oxid2Fa\Infrastructure\DbChallengeThrottle;
use DaziWeb\Oxid2Fa\Infrastructure\DbEnrollmentRepository;
use DaziWeb\Oxid2Fa\Infrastructure\DbRecoveryCodeRepository;
use DaziWeb\Oxid2Fa\Infrastructure\DbRequirementRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbEnrollmentRepository::class)]
#[CoversClass(DbRecoveryCodeRepository::class)]
#[CoversClass(DbChallengeThrottle::class)]
#[CoversClass(DbRequirementRepository::class)]
final class RepositoriesTest extends DatabaseTestCase
{
    private const USER = 'user-1';

    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new MockClock('2026-10-01 12:00:00');
    }

    public function testUnfinishedSetupIsNotActiveAndCanBeRestarted(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);

        $first = $repository->saveSetup(self::USER, 'secret-a');
        $second = $repository->saveSetup(self::USER, 'secret-b');

        $this->assertFalse($first->isActive());
        $this->assertSame($first->identifier, $second->identifier);
        $this->assertSame('secret-b', $second->secretEncrypted);
    }

    public function testActiveEnrolmentIsNeverOverwrittenByANewSetup(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollment = $repository->saveSetup(self::USER, 'secret-a');
        $repository->claimStep($enrollment->identifier, 100);
        $repository->activate($enrollment->identifier, $this->clock->now());

        $after = $repository->saveSetup(self::USER, 'secret-b');

        $this->assertSame('secret-a', $after->secretEncrypted);
        $this->assertTrue($after->isActive());
        $this->assertSame(100, $after->lastUsedStep, 'the replay protection survives a setup attempt');
    }

    public function testASetupRestartForgetsTheStepOfTheAbandonedSetup(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollment = $repository->saveSetup(self::USER, 'secret-a');
        $repository->claimStep($enrollment->identifier, 100);

        $restarted = $repository->saveSetup(self::USER, 'secret-b');

        $this->assertNull($restarted->lastUsedStep);
    }

    public function testEnrolmentCanOnlyBeActivatedOnce(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollment = $repository->saveSetup(self::USER, 'secret-a');

        $this->assertTrue($repository->activate($enrollment->identifier, $this->clock->now()));
        $this->assertFalse($repository->activate($enrollment->identifier, $this->clock->now()));
    }

    public function testALaterActivationDoesNotReplaceTheFirstOne(): void
    {
        // an UPDATE that writes the same value reports no change, so the second call must differ in time
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollment = $repository->saveSetup(self::USER, 'secret-a');
        $repository->activate($enrollment->identifier, $this->clock->now());

        $this->clock->sleep(3600);

        $this->assertFalse($repository->activate($enrollment->identifier, $this->clock->now()));
        $stored = self::$connection->fetchOne('SELECT enabled_at FROM oxid2fa_twofactor');
        $this->assertSame('2026-10-01 12:00:00', $stored);
    }

    public function testTimeStepCanOnlyBeClaimedOnceAndOnlyForwards(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollmentId = $repository->saveSetup(self::USER, 'secret-a')->identifier;

        $this->assertTrue($repository->claimStep($enrollmentId, 100));
        $this->assertFalse($repository->claimStep($enrollmentId, 100), 'replay');
        $this->assertFalse($repository->claimStep($enrollmentId, 99), 'older step');
        $this->assertTrue($repository->claimStep($enrollmentId, 101));
    }

    public function testTimestampsAreStoredInUtcWhateverTheShopTimeZone(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $enrollment = $repository->saveSetup(self::USER, 'secret-a');
        $berlin = new \DateTimeImmutable('2026-10-01 14:00:00', new \DateTimeZone('Europe/Berlin'));

        $repository->activate($enrollment->identifier, $berlin);

        $stored = self::$connection->fetchOne('SELECT enabled_at FROM oxid2fa_twofactor');
        $this->assertSame('2026-10-01 12:00:00', $stored);
        $loaded = $repository->find(self::USER);
        $this->assertSame('2026-10-01 12:00:00', $loaded?->enabledAt?->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $loaded?->enabledAt?->getTimezone()->getName());
    }

    public function testEnrolmentsAreSeparatedByAccount(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $repository->saveSetup('user-1', 'secret-1');
        $repository->saveSetup('user-2', 'secret-2');

        $this->assertSame('secret-1', $repository->find('user-1')?->secretEncrypted);
        $this->assertNull($repository->find('user-3'));
        $accounts = array_keys($repository->findAll());
        sort($accounts);
        $this->assertSame(['user-1', 'user-2'], $accounts);
        $this->assertCount(2, $repository->allEncryptedSecrets());
    }

    public function testDeletingAnEnrolmentLeavesOtherAccountsAlone(): void
    {
        $repository = new DbEnrollmentRepository($this->provider);
        $repository->saveSetup(self::USER, 'secret-a');
        $repository->saveSetup('user-2', 'secret-b');

        $repository->delete(self::USER);

        $this->assertNull($repository->find(self::USER));
        $this->assertSame('secret-b', $repository->find('user-2')?->secretEncrypted);
    }

    public function testRecoveryCodeCanBeConsumedOnlyOnce(): void
    {
        $enrollmentId = $this->enrolment();
        $codes = new DbRecoveryCodeRepository($this->provider);
        $codes->replaceAll($enrollmentId, ['hash-1', 'hash-2']);

        $this->assertTrue($codes->consume($enrollmentId, 'hash-1', $this->clock->now()));
        $this->assertFalse($codes->consume($enrollmentId, 'hash-1', $this->clock->now()));
        $this->assertFalse($codes->consume($enrollmentId, 'unknown', $this->clock->now()));
        $this->assertSame(1, $codes->countUnused($enrollmentId));
    }

    public function testARedeemedRecoveryCodeStaysRedeemedAtAnyLaterTime(): void
    {
        // an UPDATE that writes the same value reports no change, so the second call must differ in time
        $enrollmentId = $this->enrolment();
        $codes = new DbRecoveryCodeRepository($this->provider);
        $codes->replaceAll($enrollmentId, ['hash-1']);
        $this->assertTrue($codes->consume($enrollmentId, 'hash-1', $this->clock->now()));

        $this->clock->sleep(3600);

        $this->assertFalse($codes->consume($enrollmentId, 'hash-1', $this->clock->now()));
    }

    public function testACodeOfAnotherEnrolmentCannotBeConsumed(): void
    {
        $codes = new DbRecoveryCodeRepository($this->provider);
        $mine = $this->enrolment();
        $other = (new DbEnrollmentRepository($this->provider))
            ->saveSetup('user-2', 'secret')->identifier;
        $codes->replaceAll($other, ['hash-of-user-2']);

        $this->assertFalse($codes->consume($mine, 'hash-of-user-2', $this->clock->now()));
    }

    public function testReplacingRecoveryCodesInvalidatesTheOldOnes(): void
    {
        $enrollmentId = $this->enrolment();
        $codes = new DbRecoveryCodeRepository($this->provider);
        $codes->replaceAll($enrollmentId, ['old-1', 'old-2']);

        $codes->replaceAll($enrollmentId, ['new-1']);

        $this->assertFalse($codes->consume($enrollmentId, 'old-1', $this->clock->now()));
        $this->assertTrue($codes->consume($enrollmentId, 'new-1', $this->clock->now()));
        $this->assertSame(0, $codes->countUnused($enrollmentId));
    }

    public function testDeletingTheEnrolmentRemovesItsRecoveryCodes(): void
    {
        $enrollmentId = $this->enrolment();
        $codes = new DbRecoveryCodeRepository($this->provider);
        $codes->replaceAll($enrollmentId, ['hash-1']);

        (new DbEnrollmentRepository($this->provider))->delete(self::USER);

        $this->assertSame(0, $codes->countUnused($enrollmentId));
        $this->assertSame(0, (int)self::$connection->fetchOne('SELECT COUNT(*) FROM oxid2fa_twofactor_recovery_code'));
    }

    public function testAttemptsAreCountedAndTheBudgetEndsAfterFive(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);

        $numbers = [];
        for ($i = 0; $i < 7; $i++) {
            $numbers[] = $throttle->reserveAttempt(self::USER);
        }

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $numbers);
        $this->assertSame(7, $throttle->attempts(self::USER));
    }

    public function testBudgetIsPerAccount(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);
        for ($i = 0; $i < 6; $i++) {
            $throttle->reserveAttempt('user-1');
        }

        $this->assertSame(1, $throttle->reserveAttempt('user-2'));
    }

    public function testBudgetIsRestoredAfterTheLockTimeAndByReset(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);
        for ($i = 0; $i < 6; $i++) {
            $throttle->reserveAttempt(self::USER);
        }

        $this->clock->sleep(901);
        $this->assertSame(1, $throttle->reserveAttempt(self::USER), 'lock time over');

        $throttle->reserveAttempt(self::USER);
        $throttle->reset(self::USER);
        $this->assertSame(0, $throttle->attempts(self::USER));
        $this->assertSame(1, $throttle->reserveAttempt(self::USER), 'reset');
    }

    public function testDeniedAttemptsDoNotProlongTheLock(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);
        for ($i = 0; $i < 6; $i++) {
            $throttle->reserveAttempt(self::USER);
        }

        $this->clock->sleep(600);
        $throttle->reserveAttempt(self::USER); // denied, ten minutes into the lock
        $this->clock->sleep(301);

        $this->assertSame(1, $throttle->reserveAttempt(self::USER));
    }

    public function testOldFailuresDecayWithoutBeingLocked(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);
        for ($i = 0; $i < 4; $i++) {
            $throttle->reserveAttempt(self::USER);
        }

        $this->clock->sleep(901);

        $this->assertSame(1, $throttle->reserveAttempt(self::USER));
    }

    public function testTheRequirementFlagCanBeSetAndRemoved(): void
    {
        $requirements = new DbRequirementRepository($this->provider);

        $this->assertFalse($requirements->isRequired(self::USER));
        $requirements->setRequired(self::USER, true);
        $requirements->setRequired(self::USER, true); // idempotent
        $this->assertTrue($requirements->isRequired(self::USER));
        $this->assertFalse($requirements->isRequired('user-2'));

        $requirements->setRequired(self::USER, false);
        $this->assertFalse($requirements->isRequired(self::USER));
    }

    public function testListingRequiredAccounts(): void
    {
        $requirements = new DbRequirementRepository($this->provider);
        $requirements->setRequired('user-1', true);
        $requirements->setRequired('user-2', true);

        $ids = $requirements->requiredUserIds();
        sort($ids);

        $this->assertSame(['user-1', 'user-2'], $ids);
    }

    public function testReadingTheAttemptsDoesNotChangeAnything(): void
    {
        $throttle = new DbChallengeThrottle($this->provider, $this->clock);
        for ($i = 0; $i < 3; $i++) {
            $throttle->reserveAttempt(self::USER);
        }
        $this->clock->sleep(901);

        // The window is over: nothing counts any more, and looking does not delete anything
        $this->assertSame(0, $throttle->attempts(self::USER));
        $this->assertSame(1, (int)self::$connection->fetchOne('SELECT COUNT(*) FROM oxid2fa_twofactor_attempt'));
    }

    private function enrolment(): string
    {
        return (new DbEnrollmentRepository($this->provider))
            ->saveSetup(self::USER, 'secret')->identifier;
    }
}
