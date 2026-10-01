<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Core;

use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKeyFile;

final class ModuleEvents
{
    /**
     * Makes the module work without manual key handling. A key from the environment is respected,
     * an existing key file is never replaced.
     */
    public static function onActivate(): void
    {
        if (EncryptionKey::fromEnvironment()->isConfigured()) {
            return;
        }

        EncryptionKeyFile::forShop()->createIfMissing();
    }
}
