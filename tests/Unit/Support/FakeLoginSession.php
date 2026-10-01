<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\LoginSession;
use DaziWeb\Oxid2Fa\Domain\AuthenticatedLogin;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;

/**
 * Behaves like the shop session: the "auth" slot is what every protected area looks at.
 */
final class FakeLoginSession implements LoginSession
{
    public ?AuthenticatedLogin $authenticated = null;
    public ?PendingLogin $pending = null;
    public int $rotations = 0;
    public bool $destroyed = false;

    public function simulateShopPasswordLogin(string $userId): void
    {
        $this->authenticated = new AuthenticatedLogin($userId, 'token-' . $userId);
    }

    public function isFullyAuthenticated(): bool
    {
        return $this->authenticated !== null;
    }

    public function withhold(): ?AuthenticatedLogin
    {
        $login = $this->authenticated;
        $this->authenticated = null;

        return $login;
    }

    public function restore(AuthenticatedLogin $login): void
    {
        $this->authenticated = $login;
    }

    public function rotateSessionId(): void
    {
        $this->rotations++;
    }

    public function storePending(PendingLogin $pending): void
    {
        $this->pending = $pending;
    }

    public function pending(): ?PendingLogin
    {
        return $this->pending;
    }

    public function clearPending(): void
    {
        $this->pending = null;
    }

    public function abandon(): void
    {
        $this->pending = null;
        $this->authenticated = null;
        $this->destroyed = true;
    }
}
