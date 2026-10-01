<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/** A password alone is not enough for this account, and the way the visitor signed in has no second step. */
final class ChallengeRequired extends \RuntimeException
{
}
