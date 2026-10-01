<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

enum KeySource: string
{
    case Environment = 'environment';
    case File = 'file';
    case None = 'none';
}
