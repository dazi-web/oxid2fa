<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * Accounts for which an operator requires 2FA regardless of the shop wide mode.
 */
interface RequirementRepository
{
    public function isRequired(string $userId): bool;

    /** @return list<string> ids of all accounts for which 2FA is required */
    public function requiredUserIds(): array;

    public function setRequired(string $userId, bool $required): void;
}
