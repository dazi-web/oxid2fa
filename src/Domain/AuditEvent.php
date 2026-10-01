<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

enum AuditEvent: string
{
    case Enabled = '2FA_ENABLED';
    case Disabled = '2FA_DISABLED';
    case Reset = '2FA_RESET';
    case RecoveryCodesRegenerated = 'RECOVERY_CODES_REGENERATED';
    case RecoveryCodeUsed = 'RECOVERY_CODE_USED';
    case ChallengeFailed = '2FA_CHALLENGE_FAILED';
    case ChallengeLocked = '2FA_CHALLENGE_LOCKED';
    case NotOperational = '2FA_NOT_OPERATIONAL';
    case Unlocked = '2FA_UNLOCKED';
    case RequiredSet = '2FA_REQUIRED_SET';
    case RequiredCleared = '2FA_REQUIRED_CLEARED';
}
