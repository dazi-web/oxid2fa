<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Controller\Admin;

use DaziWeb\Oxid2Fa\Application\AdminOverview;
use DaziWeb\Oxid2Fa\Application\AuditReport;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;

/**
 * Tab in the user administration: lets a main administrator remove the second factor of an account whose
 * owner lost both the device and the recovery codes.
 */
class TwoFactorUserAdminController extends AbstractTwoFactorController
{
    private const LOG_ROWS = 20;

    protected $_sThisTemplate = '@oxid2fa/admin/user_twofactor';

    public function render()
    {
        $userId = (string)$this->getEditObjectId();
        $enrollments = $this->getService(EnrollmentService::class);

        $this->addTplParam('oxid', $userId);
        $this->addTplParam('mayReset', $this->isMainAdmin());
        $this->addTplParam('twoFactorActive', $userId !== '' && $enrollments->isActive($userId));
        $this->addTplParam('twoFactorRequired', $this->isRequired($userId));
        $audit = $this->getService(AuditReport::class);
        $this->addTplParam('auditRows', $userId === '' ? [] : $audit->recent(self::LOG_ROWS, $userId));
        $this->addTplParam('isSelf', $userId === $this->currentUserId());

        return parent::render();
    }

    public function reset(): void
    {
        $userId = (string)$this->getEditObjectId();
        if ($userId === '' || !$this->isMainAdmin()) {
            return;
        }

        try {
            $this->getService(EnrollmentService::class)
                ->reset($userId, $this->currentUserId());
        } catch (\DomainException) {
            $this->showError('selfReset');
        }
    }

    public function setRequired(): void
    {
        $userId = (string)$this->getEditObjectId();
        if ($userId === '' || !$this->isMainAdmin()) {
            return;
        }

        $this->getService(AdminOverview::class)->setRequired(
            $userId,
            $this->requestParameter('required') === '1',
            $this->currentUserId()
        );
    }

    private function isRequired(string $userId): bool
    {
        foreach ($this->getService(AdminOverview::class)->rows() as $row) {
            if ($row->userId === $userId) {
                return $row->required;
            }
        }

        return false;
    }
}
