<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DaziWeb\Oxid2Fa\Application\AccountCleanup;
use DaziWeb\Oxid2Fa\Application\PasswordOnlyLoginGuard;
use OxidEsales\Eshop\Core\Exception\UserException;

/**
 * @eshopExtension
 *
 * Chain extension of the user model:
 * - a deleted account must not leave its second factor behind;
 * - a password login outside the admin area (shop front end, GraphQL, other modules) is refused for administrators
 *   who owe a second factor, because those entry points have no second step.
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

    /**
     * Runs after the password was accepted and before the shop writes the login into the session, so refusing here
     * leaves nothing behind. The message is the shop's usual "invalid login": it must not confirm the password.
     */
    protected function onLogin($userName, $password): void
    {
        parent::onLogin($userName, $password);

        if (!$this->isLoaded() || $this->isAdmin() || (string)$this->getFieldData('oxrights') === 'user') {
            return;
        }

        if (!$this->getService(PasswordOnlyLoginGuard::class)->isAllowed((string)$this->getId())) {
            throw oxNew(UserException::class, 'ERROR_MESSAGE_USER_NOVALIDLOGIN');
        }
    }
}
