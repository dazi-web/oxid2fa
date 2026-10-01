<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DaziWeb\Oxid2Fa\Application\RequestContext;
use OxidEsales\Eshop\Core\Registry;

final class OxidRequestContext implements RequestContext
{
    public function origin(): array
    {
        $server = Registry::getUtilsServer();
        $origin = [];

        // REMOTE_ADDR is what the web server actually saw; the forwarded header is only a claim of the client
        // (or of a proxy) and is logged next to it, never instead of it.
        $remote = $server->getServerVar('REMOTE_ADDR');
        if (is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false) {
            $origin['ip'] = $remote;
        }

        $forwarded = $server->getServerVar('HTTP_X_FORWARDED_FOR');
        $firstHop = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : '';
        $isNewAddress = $firstHop !== '' && $firstHop !== ($origin['ip'] ?? null);
        if ($isNewAddress && filter_var($firstHop, FILTER_VALIDATE_IP) !== false) {
            $origin['forwarded_for'] = $firstHop;
        }

        return $origin;
    }
}
