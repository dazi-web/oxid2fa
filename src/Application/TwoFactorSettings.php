<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\Mode;

final readonly class TwoFactorSettings
{
    public const MIN_RECOVERY_CODES = 4;
    public const MAX_RECOVERY_CODES = 20;

    /**
     * @param bool $operational false while no encryption key is configured: the feature then stays
     *                          switched off instead of locking anybody out
     */
    public function __construct(
        private Mode $adminMode,
        private bool $operational,
        private bool $recoveryCodesEnabled,
        private int $recoveryCodeCount,
        private string $issuer,
        private bool $blockWithoutKey = true,
    ) {
    }

    public function mode(): Mode
    {
        return $this->operational ? $this->adminMode : Mode::Disabled;
    }

    /** True if the shop owner asked for 2FA but it cannot run, i.e. the encryption key is missing. */
    public function isConfiguredButInoperative(): bool
    {
        return !$this->operational && $this->adminMode !== Mode::Disabled;
    }

    /** Accounts with active 2FA cannot log in while the key is missing (otherwise: the password alone suffices). */
    public function blocksEnrolledAccountsWithoutKey(): bool
    {
        return $this->blockWithoutKey;
    }

    public function enrollmentAllowed(): bool
    {
        return $this->mode() !== Mode::Disabled;
    }

    public function isOperational(): bool
    {
        return $this->operational;
    }

    public function recoveryCodesEnabled(): bool
    {
        return $this->recoveryCodesEnabled;
    }

    public function recoveryCodeCount(): int
    {
        return max(self::MIN_RECOVERY_CODES, min(self::MAX_RECOVERY_CODES, $this->recoveryCodeCount));
    }

    public function issuer(): string
    {
        return $this->issuer;
    }
}
