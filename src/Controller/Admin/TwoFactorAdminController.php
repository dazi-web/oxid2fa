<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use DaziWeb\Oxid2Fa\Application\ChallengeRejectedException;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use DaziWeb\Oxid2Fa\Application\SetupService;
use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

/**
 * Security page of the logged-in administrator: set up, review and switch off their own second factor.
 */
class TwoFactorAdminController extends AbstractTwoFactorController
{
    protected $_sThisTemplate = '@oxid2fa/admin/twofactor';

    public function render()
    {
        $settings = $this->getService(TwoFactorSettings::class);
        $enrollments = $this->getService(EnrollmentService::class);
        $enrollment = $enrollments->find($this->currentUserId());

        $this->addTplParam('operational', $settings->isOperational());
        $this->addTplParam('enrollmentAllowed', $settings->enrollmentAllowed());
        $this->addTplParam('recoveryCodesEnabled', $settings->recoveryCodesEnabled());

        if ($enrollment?->isActive()) {
            $this->addTplParam('status', 'active');
            $this->addTplParam('enabledAt', $enrollment->enabledAt?->format($this->dateFormat()));
            if ($settings->recoveryCodesEnabled()) {
                $this->addTplParam('recoveryCodesLeft', $enrollments->remainingRecoveryCodes($enrollment));
                $this->addTplParam('recoveryCodesLow', $enrollments->recoveryCodesRunningLow($enrollment));
            }
        } elseif ($enrollment !== null && $settings->enrollmentAllowed()) {
            $setup = $this->getService(SetupService::class)->beginSetup($this->currentUserId());
            $this->addTplParam('status', 'setup');
            $this->addTplParam('qrCodeSvg', $this->qrCodeSvg($setup->provisioningUri));
            $this->addTplParam('setupKey', $setup->groupedSecret());
        } else {
            $this->addTplParam('status', 'inactive');
        }

        return parent::render();
    }

    public function startSetup(): void
    {
        try {
            $this->getService(SetupService::class)->beginSetup($this->currentUserId());
        } catch (\LogicException) {
            // Already active (second tab) or switched off meanwhile: the page shows the current state.
        }
    }

    public function confirmSetup(): void
    {
        try {
            $codes = $this->getService(SetupService::class)
                ->confirmSetup($this->currentUserId(), $this->submittedCode());
        } catch (\LogicException) {
            return;
        }

        if ($codes === null) {
            $this->showError('invalid');

            return;
        }

        $this->addTplParam('recoveryCodes', $codes);
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->withActiveEnrollment(function (EnrollmentService $enrollments, Enrollment $enrollment): void {
            $codes = $enrollments->regenerateRecoveryCodes($enrollment, $this->submittedCode());
            $this->addTplParam('recoveryCodes', $codes);
        });
    }

    public function disable(): void
    {
        $this->withActiveEnrollment(function (EnrollmentService $enrollments, Enrollment $enrollment): void {
            $enrollments->disable($enrollment, $this->submittedCode());
        });
    }

    private function withActiveEnrollment(callable $action): void
    {
        $enrollments = $this->getService(EnrollmentService::class);
        $enrollment = $enrollments->find($this->currentUserId());
        if ($enrollment?->isActive() !== true) {
            return;
        }

        try {
            $action($enrollments, $enrollment);
        } catch (ChallengeRejectedException $rejected) {
            $this->showError($rejected->result === ChallengeResult::Locked ? 'locked' : 'invalid');
        }
    }
}
