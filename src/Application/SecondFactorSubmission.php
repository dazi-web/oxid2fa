<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * The code a client sends together with its password where the sign-in has no second page, such as the GraphQL API.
 * It lives for one request: whoever provides it removes it again, and the check consumes it.
 */
final class SecondFactorSubmission
{
    private ?string $code = null;

    public function provide(string $code): void
    {
        $this->code = $code;
    }

    /** Hands the code out once. */
    public function take(): ?string
    {
        $code = $this->code;
        $this->code = null;

        return $code;
    }
}
