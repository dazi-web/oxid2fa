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
use DaziWeb\Oxid2Fa\Application\EnrollmentService;

/**
 * For main administrators: all back end accounts with their 2FA status, require 2FA per account, reset others.
 */
class TwoFactorOverviewController extends AbstractTwoFactorController
{
    private const LOG_ROWS = 50;

    protected $_sThisTemplate = '@oxid2fa/admin/twofactor_overview';

    public function render()
    {
        $this->addTplParam('mayManage', $this->isMainAdmin());
        if ($this->isMainAdmin()) {
            $this->addTplParam('adminRows', $this->rows());
            $this->addTplParam('auditRows', $this->getService(AuditReport::class)->recent(self::LOG_ROWS));
        }

        return parent::render();
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
    private function rows(): array
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
}
