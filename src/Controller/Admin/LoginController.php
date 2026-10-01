<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use DaziWeb\Oxid2Fa\Application\TwoFactorGate;

/**
 * @eshopExtension
 *
 * Chain extension of the admin login. The shop checks username and password and writes the login into the
 * session; the gate then holds that login back if a second factor is due.
 *
 * NOTE: class must not be final.
 *
 * @mixin \OxidEsales\Eshop\Application\Controller\Admin\LoginController
 */
class LoginController extends LoginController_parent
{
    public function checklogin()
    {
        $nextController = parent::checklogin();

        $step = $this->getService(TwoFactorGate::class)->afterPasswordVerified();

        return $step === null ? $nextController : 'oxid2fa_twofactor_challenge';
    }
}
