<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;

/**
 * The audit log lives next to the shop's own log files, wherever that directory is.
 */
final readonly class AuditLogFile
{
    private const FILE_NAME = 'oxid2fa_audit.log';

    public function __construct(private ContextInterface $context)
    {
    }

    public function path(): string
    {
        return dirname($this->context->getLogFilePath()) . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }
}
