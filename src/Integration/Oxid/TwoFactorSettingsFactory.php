<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use DaziWeb\Oxid2Fa\Core\Module;
use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\Mode;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;

final readonly class TwoFactorSettingsFactory
{
    public const ADMIN_MODE = 'oxid2fa_AdminMode';
    public const RECOVERY_CODES = 'oxid2fa_RecoveryCodes';
    public const RECOVERY_CODE_COUNT = 'oxid2fa_RecoveryCodeCount';
    public const ISSUER = 'oxid2fa_Issuer';
    public const BEHAVIOR_WITHOUT_KEY = 'oxid2fa_BehaviorWithoutKey';

    public function __construct(
        private ModuleSettingServiceInterface $settings,
        private EncryptionKey $key,
    ) {
    }

    public function create(): TwoFactorSettings
    {
        return new TwoFactorSettings(
            Mode::fromSetting((string)$this->settings->getString(self::ADMIN_MODE, Module::MODULE_ID)),
            $this->key->isConfigured(),
            $this->settings->getBoolean(self::RECOVERY_CODES, Module::MODULE_ID),
            $this->settings->getInteger(self::RECOVERY_CODE_COUNT, Module::MODULE_ID),
            $this->issuer(),
            (string)$this->settings->getString(self::BEHAVIOR_WITHOUT_KEY, Module::MODULE_ID) !== 'allow',
        );
    }

    private function issuer(): string
    {
        $issuer = trim((string)$this->settings->getString(self::ISSUER, Module::MODULE_ID));
        if ($issuer !== '') {
            return $issuer;
        }

        $shopName = trim((string)Registry::getConfig()->getActiveShop()->getFieldData('oxname'));

        return $shopName !== '' ? $shopName : 'OXID eShop';
    }
}
