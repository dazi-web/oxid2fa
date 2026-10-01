<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

interface RequestContext
{
    /**
     * Where the current request comes from, as extra audit log fields. Empty outside a web request (CLI).
     *
     * @return array<string, string>
     */
    public function origin(): array;
}
