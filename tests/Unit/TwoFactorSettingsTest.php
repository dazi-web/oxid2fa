<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoFactorSettings::class)]
#[CoversClass(Mode::class)]
final class TwoFactorSettingsTest extends TestCase
{
    #[DataProvider('recoveryCodeCounts')]
    public function testTheNumberOfRecoveryCodesStaysInSensibleBounds(int $configured, int $expected): void
    {
        $settings = new TwoFactorSettings(Mode::Optional, true, true, $configured, 'Shop');

        $this->assertSame($expected, $settings->recoveryCodeCount());
    }

    public static function recoveryCodeCounts(): array
    {
        return [
            'negative' => [-5, 4],
            'zero' => [0, 4],
            'lower bound' => [4, 4],
            'typical' => [10, 10],
            'upper bound' => [20, 20],
            'absurd' => [10000, 20],
        ];
    }

    #[DataProvider('settingValues')]
    public function testAnUnknownModeNeverSwitchesTheFeatureOnForEverybody(string $value, Mode $expected): void
    {
        $this->assertSame($expected, Mode::fromSetting($value));
    }

    public static function settingValues(): array
    {
        return [
            'disabled' => ['disabled', Mode::Disabled],
            'optional' => ['optional', Mode::Optional],
            'mandatory' => ['mandatory', Mode::Mandatory],
            'empty' => ['', Mode::Disabled],
            'garbage' => ['everyone', Mode::Disabled],
            'wrong case' => ['MANDATORY', Mode::Disabled],
        ];
    }

    public function testWithoutAKeyNoModeIsActive(): void
    {
        $settings = new TwoFactorSettings(Mode::Mandatory, false, true, 10, 'Shop');

        $this->assertSame(Mode::Disabled, $settings->mode());
        $this->assertFalse($settings->enrollmentAllowed());
    }

    public function testAMissingKeyIsOnlyAProblemWhereTheOperatorWantsTwoFactor(): void
    {
        $wanted = new TwoFactorSettings(Mode::Optional, false, true, 10, 'Shop');
        $notWanted = new TwoFactorSettings(Mode::Disabled, false, true, 10, 'Shop');
        $working = new TwoFactorSettings(Mode::Optional, true, true, 10, 'Shop');

        $this->assertTrue($wanted->isConfiguredButInoperative());
        $this->assertFalse($notWanted->isConfiguredButInoperative());
        $this->assertFalse($working->isConfiguredButInoperative());
    }
}
