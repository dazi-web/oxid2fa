<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use DaziWeb\Oxid2Fa\Application\QrCodeRenderer;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

/**
 * What the 2FA admin pages have in common: who is logged in, what was submitted, how dates are shown.
 */
abstract class AbstractTwoFactorController extends AdminController
{
    protected function currentUserId(): string
    {
        return (string)Registry::getSession()->getVariable('auth');
    }

    protected function isMainAdmin(): bool
    {
        return (bool)Registry::getSession()->getVariable('malladmin');
    }

    protected function requestParameter(string $name): string
    {
        return (string)Registry::getRequest()->getRequestParameter($name);
    }

    protected function submittedCode(): string
    {
        return $this->requestParameter('code');
    }

    protected function dateFormat(): string
    {
        return Registry::getLang()->getLanguageAbbr() === 'de' ? 'd.m.Y' : 'Y-m-d';
    }

    protected function qrCodeSvg(string $content): string
    {
        return $this->getService(QrCodeRenderer::class)->svg($content);
    }

    protected function showError(string $error): void
    {
        $this->addTplParam('error', $error);
    }
}
