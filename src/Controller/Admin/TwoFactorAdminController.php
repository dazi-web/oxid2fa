<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use DaziWeb\Oxid2Fa\Application\AdminOverview;
use DaziWeb\Oxid2Fa\Application\AdminOverviewRow;
use DaziWeb\Oxid2Fa\Application\AuditReport;
use DaziWeb\Oxid2Fa\Application\ChallengeRejectedException;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use DaziWeb\Oxid2Fa\Application\SetupService;
use DaziWeb\Oxid2Fa\Application\TwoFactorSettings;
use DaziWeb\Oxid2Fa\Domain\ChallengeResult;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

/**
 * The one 2FA page (Service → 2FA). Everybody sets up, reviews and switches off their own second factor. Main
 * administrators also see all back end accounts (require 2FA, release a lock, reset) and the audit log.
 */
class TwoFactorAdminController extends AbstractTwoFactorController
{
    private const LOG_ROWS = 50;

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

        $this->addTplParam('mayManage', $this->isMainAdmin());
        if ($this->isMainAdmin()) {
            $this->addTplParam('adminRows', $this->adminRows());
            $this->addTplParam('auditRows', $this->getService(AuditReport::class)->recent(self::LOG_ROWS));
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

    public function setRequired(): void
    {
        if (!$this->isMainAdmin()) {
            return;
        }

        $this->getService(AdminOverview::class)->setRequired(
            $this->requestParameter('userid'),
            $this->requestParameter('required') === '1',
            $this->currentUserId()
        );
    }

    public function unlock(): void
    {
        if (!$this->isMainAdmin()) {
            return;
        }

        $this->getService(AdminOverview::class)->unlock($this->requestParameter('userid'), $this->currentUserId());
    }

    public function resetUser(): void
    {
        if (!$this->isMainAdmin()) {
            return;
        }

        try {
            $this->getService(EnrollmentService::class)
                ->reset($this->requestParameter('userid'), $this->currentUserId());
        } catch (\DomainException) {
            $this->showError('selfReset');
        }
    }

    /** @return list<array<string, mixed>> */
    private function adminRows(): array
    {
        return array_map(fn (AdminOverviewRow $row): array => [
            'userId' => $row->userId,
            'loginName' => $row->loginName,
            'status' => $row->status,
            'enabledAt' => $row->enabledAt?->format($this->dateFormat()),
            'required' => $row->required,
            'failedAttempts' => $row->failedAttempts,
            'locked' => $row->isLocked(),
            'isSelf' => $row->userId === $this->currentUserId(),
        ], $this->getService(AdminOverview::class)->rows());
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
