<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DaziWeb\Oxid2Fa\Application\AccountCleanup;

/**
 * @eshopExtension
 *
 * Chain extension of the user model: a deleted account must not leave its second factor behind.
 *
 * NOTE: class must not be final.
 *
 * @mixin \OxidEsales\Eshop\Application\Model\User
 */
class User extends User_parent
{
    public function delete($oxid = null)
    {
        $userId = (string)($oxid ?: $this->getId());

        $deleted = parent::delete($oxid);
        if ($deleted && $userId !== '') {
            $this->getService(AccountCleanup::class)->forget($userId);
        }

        return $deleted;
    }
}
