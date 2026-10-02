<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\SecondFactorDecision;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\TwoFactorFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecondFactorDecision::class)]
final class SecondFactorDecisionTest extends TestCase
{
    private const ADMIN = 'admin-1';

    #[DataProvider('cases')]
    public function testStepAfterThePasswordCheck(
        Mode $mode,
        bool $hasActiveEnrollment,
        bool $requiredByOperator,
        ?PendingStep $expected,
    ): void {
        // Given the shop's mode and an admin who has or has not set up 2FA, and may be flagged by the operator
        $f = new TwoFactorFixture($mode);
        if ($hasActiveEnrollment) {
            $enrolled = new TwoFactorFixture();
            $enrolled->enrollUser(self::ADMIN);
            $f->enrollments->copyFrom($enrolled->enrollments);
        }
        $f->requirements->setRequired(self::ADMIN, $requiredByOperator);

        // When it is asked what the login still requires
        $step = $f->decision->stepFor(self::ADMIN);

        // Then
        $this->assertSame($expected, $step);
    }

    /** @return array<string, array{Mode, bool, bool, ?PendingStep}> */
    public static function cases(): array
    {
        return [
            'disabled, not configured' => [Mode::Disabled, false, false, null],
            'disabled, enabled for the user' => [Mode::Disabled, true, false, null],
            'disabled wins over the flag' => [Mode::Disabled, false, true, null],
            'optional, not configured' => [Mode::Optional, false, false, null],
            'optional, enabled' => [Mode::Optional, true, false, PendingStep::VerifyCode],
            'optional, flagged, not configured' => [Mode::Optional, false, true, PendingStep::SetupRequired],
            'optional, flagged, enabled' => [Mode::Optional, true, true, PendingStep::VerifyCode],
            'mandatory, enabled' => [Mode::Mandatory, true, false, PendingStep::VerifyCode],
            'mandatory, not configured' => [Mode::Mandatory, false, false, PendingStep::SetupRequired],
        ];
    }
}
