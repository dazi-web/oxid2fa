<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Tests\Unit\Support\InMemoryRecoveryCodeRepository;
use DaziWeb\Oxid2Fa\Application\RecoveryCodeService;
use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RecoveryCodeService::class)]
final class RecoveryCodeServiceTest extends TestCase
{
    private RecoveryCodeService $service;

    protected function setUp(): void
    {
        $this->service = new RecoveryCodeService(
            new InMemoryRecoveryCodeRepository(),
            new EncryptionKey(str_repeat('k', 32)),
            new TwoFactorSettings(Mode::Optional, true, true, 10, 'Testshop'),
            new MockClock('2026-10-01 12:00:00')
        );
    }

    public function testNothingIsIssuedOrAcceptedWhenTheShopDoesNotUseRecoveryCodes(): void
    {
        $service = new RecoveryCodeService(
            new InMemoryRecoveryCodeRepository(),
            new EncryptionKey(str_repeat('k', 32)),
            new TwoFactorSettings(Mode::Optional, true, false, 10, 'Testshop'),
            new MockClock('2026-10-01 12:00:00')
        );

        $this->assertSame([], $service->issue('e1'));
        $this->assertFalse($service->consume('e1', 'AAAAA-AAAAA'));
    }

    public function testExistingCodesStopWorkingWhenTheShopSwitchesRecoveryCodesOff(): void
    {
        // Given codes that were issued while recovery codes were in use
        $repository = new InMemoryRecoveryCodeRepository();
        $key = new EncryptionKey(str_repeat('k', 32));
        $clock = new MockClock('2026-10-01 12:00:00');
        $withCodes = new TwoFactorSettings(Mode::Optional, true, true, 10, 'Shop');
        $withoutCodes = new TwoFactorSettings(Mode::Optional, true, false, 10, 'Shop');
        $inUse = new RecoveryCodeService($repository, $key, $withCodes, $clock);
        $code = $inUse->issue('e1')[0];

        // When the operator switches recovery codes off
        $switchedOff = new RecoveryCodeService($repository, $key, $withoutCodes, $clock);

        // Then the old codes can no longer be used to log in
        $this->assertFalse($switchedOff->consume('e1', $code));
        $this->assertTrue($inUse->consume('e1', $code), 'the code itself was valid and unused');
    }

    public function testGeneratesTheRequestedNumberOfDistinctCodes(): void
    {
        $codes = $this->service->issue('e1');

        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $codes[0]);
    }

    public function testValidCodeWorksOnce(): void
    {
        $code = $this->service->issue('e1')[0];

        $this->assertTrue($this->service->consume('e1', $code));
        $this->assertFalse($this->service->consume('e1', $code), 'used code');
        $this->assertSame(9, $this->service->remaining('e1'));
    }

    public function testCodeIsAcceptedRegardlessOfCaseAndSeparators(): void
    {
        $code = $this->service->issue('e1')[0];

        $this->assertTrue($this->service->consume('e1', strtolower(str_replace('-', ' ', $code))));
    }

    public function testWrongCodeIsRejected(): void
    {
        $this->service->issue('e1');

        $this->assertFalse($this->service->consume('e1', 'AAAAA-AAAAA'));
        $this->assertFalse($this->service->consume('e1', ''));
        $this->assertFalse($this->service->consume('e1', 'short'));
    }

    public function testCodeOfAnotherEnrolmentIsRejected(): void
    {
        $code = $this->service->issue('e1')[0];
        $this->service->issue('e2');

        $this->assertFalse($this->service->consume('e2', $code));
    }

    public function testRegenerationInvalidatesOldCodes(): void
    {
        $old = $this->service->issue('e1')[0];

        $new = $this->service->issue('e1');

        $this->assertFalse($this->service->consume('e1', $old));
        $this->assertTrue($this->service->consume('e1', $new[0]));
    }

    public function testSecondUseOfTheSameCodeLosesTheRace(): void
    {
        // Given two requests carrying the same code; the repository contract makes consume() atomic
        $code = $this->service->issue('e1')[0];

        $results = [$this->service->consume('e1', $code), $this->service->consume('e1', $code)];

        // Then exactly one wins (the database level guarantee is covered by the integration test)
        $this->assertSame([true, false], $results);
    }
}
