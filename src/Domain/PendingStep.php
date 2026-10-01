<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

/**
 * Where a login that has passed the password check currently stands. A login that has completed
 * every step is not "pending" anymore: it is a regular shop session.
 */
enum PendingStep: string
{
    case VerifyCode = 'verify';
    case SetupRequired = 'setup';
    case AcknowledgeRecoveryCodes = 'acknowledge';
    case Unavailable = 'unavailable';
}
