<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use OxidEsales\Eshop\Core\Controller\BaseController;
use OxidEsales\Eshop\Core\Registry;
use DaziWeb\Oxid2Fa\Application\TwoFactorLoginFlow;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\PendingStep;

/**
 * Second login step. Reachable only while a login is pending after a correct password; at that point
 * the session carries no shop authentication, so nothing else in the back end is accessible.
 */
class TwoFactorChallengeController extends AbstractTwoFactorController
{
    protected $_sThisTemplate = '@oxid2fa/admin/twofactor_challenge';

    public function __construct()
    {
        Registry::getConfig()->setConfigParam('blAdmin', true);
    }

    /**
     * Skips AdminController::render() on purpose: like the core login it renders without a logged-in admin.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function render()
    {
        $pending = $this->flow()->pending();
        BaseController::render();

        $step = $pending->step ?? PendingStep::VerifyCode;
        $this->addTplParam('step', $step->value);

        if ($step === PendingStep::SetupRequired) {
            $setup = $this->flow()->beginSetup();
            $this->addTplParam('qrCodeSvg', $setup === null ? null : $this->qrCodeSvg($setup->provisioningUri));
            $this->addTplParam('setupKey', $setup?->groupedSecret());
        }

        return $this->_sThisTemplate;
    }

    public function verify(): ?string
    {
        $result = $this->flow()->verifyCode($this->submittedCode());
        if ($result === ChallengeResult::Passed) {
            return 'admin_start';
        }

        $this->showError($result === ChallengeResult::Locked ? 'locked' : 'invalid');

        return null;
    }

    public function confirmSetup(): ?string
    {
        $codes = $this->flow()->confirmSetup($this->submittedCode());
        if ($codes === null) {
            $this->showError('invalid');

            return null;
        }
        if ($codes === []) {
            return 'admin_start';
        }

        $this->addTplParam('recoveryCodes', $codes);

        return null;
    }

    public function reissueRecoveryCodes(): ?string
    {
        $codes = $this->flow()->reissueRecoveryCodes();
        if ($codes !== null) {
            $this->addTplParam('recoveryCodes', $codes);
        }

        return null;
    }

    public function acknowledgeRecoveryCodes(): ?string
    {
        if (!Registry::getRequest()->getRequestParameter('saved') || !$this->flow()->acknowledgeRecoveryCodes()) {
            $this->showError('notSaved');

            return null;
        }

        return 'admin_start';
    }

    public function logout(): void
    {
        $this->flow()->abandon();
        Registry::getUtils()->redirect('index.php', true, 302);
    }

    /**
     * Without a pending login there is nothing to do here. State-changing requests additionally need the
     * session's CSRF token.
     */
    protected function authorize()
    {
        if ($this->flow()->pending() === null) {
            return false;
        }

        return !Registry::getRequest()->getRequestEscapedParameter('fnc')
            || Registry::getSession()->checkSessionChallenge();
    }

    private function flow(): TwoFactorLoginFlow
    {
        return $this->getService(TwoFactorLoginFlow::class);
    }
}
