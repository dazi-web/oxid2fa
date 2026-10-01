<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

final readonly class SetupData
{
    public function __construct(
        public string $secret,
        public string $provisioningUri,
    ) {
    }

    /** Groups of four are easier to read and to type; authenticator apps ignore the spaces. */
    public function groupedSecret(): string
    {
        return trim(chunk_split($this->secret, 4, ' '));
    }
}
