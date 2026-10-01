<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

enum Mode: string
{
    case Disabled = 'disabled';
    case Optional = 'optional';
    case Mandatory = 'mandatory';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Disabled;
    }
}
