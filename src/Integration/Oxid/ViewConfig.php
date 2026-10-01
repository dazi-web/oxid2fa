<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DaziWeb\Oxid2Fa\Application\KeyHealth;
use DaziWeb\Oxid2Fa\Application\KeyHealthChecker;

/**
 * @eshopExtension
 *
 * Makes the key check available to the module settings page (`oViewConf.getOxid2faKeyHealth()`).
 *
 * NOTE: class must not be final.
 *
 * @mixin \OxidEsales\Eshop\Core\ViewConfig
 */
class ViewConfig extends ViewConfig_parent
{
    public function getOxid2faKeyHealth(): KeyHealth
    {
        return $this->getService(KeyHealthChecker::class)->inspect();
    }
}
