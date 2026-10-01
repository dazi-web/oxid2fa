<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuthenticatedLogin;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;

/**
 * The only place where the shop's session representation of "logged in" is known.
 */
interface LoginSession
{
    /**
     * Removes the authentication the shop has just written and hands it over, so that nothing
     * in the system treats the visitor as logged in until it is explicitly restored.
     */
    public function withhold(): ?AuthenticatedLogin;

    public function restore(AuthenticatedLogin $login): void;

    public function rotateSessionId(): void;

    public function storePending(PendingLogin $pending): void;

    public function pending(): ?PendingLogin;

    public function clearPending(): void;

    /** Logout while the second factor is still open: drops the pending state and the whole session. */
    public function abandon(): void;
}
