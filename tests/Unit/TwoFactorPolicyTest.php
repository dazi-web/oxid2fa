<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Domain\PendingStep;
use DaziWeb\Oxid2Fa\Domain\TwoFactorPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoFactorPolicy::class)]
final class TwoFactorPolicyTest extends TestCase
{
    #[DataProvider('cases')]
    public function testStepAfterThePasswordCheck(Mode $mode, bool $hasActiveEnrollment, ?PendingStep $expected): void
    {
        // Given a context with the shop's mode, and a user who has or has not set up 2FA
        // When the policy is asked what the login requires
        $requirement = (new TwoFactorPolicy())->stepFor($mode, $hasActiveEnrollment);

        // Then
        $this->assertSame($expected, $requirement);
    }

    public static function cases(): array
    {
        return [
            'disabled globally, user not configured' => [Mode::Disabled, false, null],
            'disabled globally, user enabled' => [Mode::Disabled, true, null],
            'optional, user not configured' => [Mode::Optional, false, null],
            'optional, user enabled' => [Mode::Optional, true, PendingStep::VerifyCode],
            'mandatory, user enabled' => [Mode::Mandatory, true, PendingStep::VerifyCode],
            'mandatory, user not configured' => [Mode::Mandatory, false, PendingStep::SetupRequired],
        ];
    }

    #[DataProvider('requiredForUserCases')]
    public function testStepWhenTheOperatorRequiresItForThisUser(
        Mode $mode,
        bool $hasActiveEnrollment,
        ?PendingStep $expected
    ): void {
        $requirement = (new TwoFactorPolicy())->stepFor($mode, $hasActiveEnrollment, true);

        $this->assertSame($expected, $requirement);
    }

    public static function requiredForUserCases(): array
    {
        return [
            'optional, user flagged, not configured' => [Mode::Optional, false, PendingStep::SetupRequired],
            'optional, user flagged, enabled' => [Mode::Optional, true, PendingStep::VerifyCode],
            'disabled globally wins over the flag' => [Mode::Disabled, false, null],
        ];
    }
}
