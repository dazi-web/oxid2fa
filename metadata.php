<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

/**
 * Metadata version
 */

use DaziWeb\Oxid2Fa\Controller\Admin\LoginController;
use DaziWeb\Oxid2Fa\Controller\Admin\TwoFactorAdminController;
use DaziWeb\Oxid2Fa\Controller\Admin\TwoFactorChallengeController;
use DaziWeb\Oxid2Fa\Controller\Admin\TwoFactorUserAdminController;
use DaziWeb\Oxid2Fa\Integration\Oxid\User;
use DaziWeb\Oxid2Fa\Integration\Oxid\ViewConfig;

$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id'          => 'oxid2fa',
    'title'       => 'Two-factor authentication (OXID2FA)',
    'description' => 'TOTP two-factor authentication for shop administrators',
    'version'     => '1.1.1',
    'author'      => 'dazi-web',
    'url'         => '',
    'email'       => '',
    'extend'      => [
        \OxidEsales\Eshop\Core\ViewConfig::class => ViewConfig::class,
        \OxidEsales\Eshop\Application\Model\User::class => User::class,
        \OxidEsales\Eshop\Application\Controller\Admin\LoginController::class => LoginController::class,
    ],
    'controllers' => [
        'oxid2fa_twofactor_challenge' => TwoFactorChallengeController::class,
        'oxid2fa_admin_twofactor' => TwoFactorAdminController::class,
        'oxid2fa_admin_user_twofactor' => TwoFactorUserAdminController::class,
    ],
    'events'      => [
        'onActivate' => '\\DaziWeb\\Oxid2Fa\\Core\\ModuleEvents::onActivate',
    ],
    'settings' => [
        [
            'group'       => 'oxid2fa_twofactor',
            'name'        => 'oxid2fa_AdminMode',
            'type'        => 'select',
            'constraints' => 'disabled|optional|mandatory',
            'value'       => 'optional'
        ],
        [
            'group' => 'oxid2fa_twofactor',
            'name'  => 'oxid2fa_RecoveryCodes',
            'type'  => 'bool',
            'value' => true
        ],
        [
            'group' => 'oxid2fa_twofactor',
            'name'  => 'oxid2fa_RecoveryCodeCount',
            'type'  => 'num',
            'value' => 10
        ],
        [
            'group' => 'oxid2fa_twofactor',
            'name'  => 'oxid2fa_Issuer',
            'type'  => 'str',
            'value' => ''
        ],
        [
            'group'       => 'oxid2fa_twofactor',
            'name'        => 'oxid2fa_BehaviorWithoutKey',
            'type'        => 'select',
            'constraints' => 'block|allow',
            'value'       => 'block'
        ]
    ],
];
