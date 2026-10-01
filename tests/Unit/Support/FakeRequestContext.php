<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\RequestContext;

final class FakeRequestContext implements RequestContext
{
    /** @var array<string, string> */
    public array $origin = ['ip' => '203.0.113.7'];

    public function origin(): array
    {
        return $this->origin;
    }
}
