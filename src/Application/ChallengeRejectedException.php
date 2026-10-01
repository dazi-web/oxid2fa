<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\ChallengeResult;

final class ChallengeRejectedException extends \RuntimeException
{
    public function __construct(public readonly ChallengeResult $result)
    {
        parent::__construct('Second factor was not accepted.');
    }
}
