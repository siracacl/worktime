<?php

/**
 * SPDX-FileCopyrightText: 2026 Axel Deffner <axel@cpcmomentum.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\WorkTime\Service;

use DateTime;
use OCA\WorkTime\Db\OvertimePayout;
use OCA\WorkTime\Db\OvertimePayoutMapper;

class OvertimePayoutService {

    public function __construct(
        private OvertimePayoutMapper $mapper,
        private AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return OvertimePayout[]
     */
    public function findActiveByEmployeeAndYear(int $employeeId, int $year): array {
        return $this->mapper->findActiveByEmployeeAndYear($employeeId, $year);
    }

    /**
     * @param int[] $employeeIds
     * @return OvertimePayout[]
     */
    public function findActiveByEmployeeIdsAndYear(array $employeeIds, int $year): array {
        return $this->mapper->findActiveByEmployeeIdsAndYear($employeeIds, $year);
    }

    /**
     * Sum of active paid-out minutes for an employee and year, optionally only up
     * to and including a given effective month.
     */
    public function sumPayoutMinutes(int $employeeId, int $year, int $uptoMonth = 12): int {
        return $this->mapper->sumMinutes($employeeId, $year, $uptoMonth);
    }

    /**
     * Record a paid-out chunk of overtime. The amount reduces the running
     * flextime balance from the given effective month onward.
     */
    public function create(
        int $employeeId,
        int $year,
        int $month,
        int $minutes,
        ?string $note,
        string $currentUserId,
    ): OvertimePayout {
        $payout = new OvertimePayout();
        $payout->setEmployeeId($employeeId);
        $payout->setYear($year);
        $payout->setMonth($month);
        $payout->setMinutes($minutes);
        $payout->setNote($note);
        $payout->setCreatedBy($currentUserId);
        $payout->setCreatedAt(new DateTime());
        $payout->setUpdatedAt(new DateTime());

        $result = $this->mapper->insert($payout);

        $this->auditLogService->logCreate(
            $currentUserId, 'overtime_payout', $result->getId(),
            $result->jsonSerialize()
        );

        return $result;
    }

    /**
     * Cancel (soft-delete) a payout, restoring the previous balance.
     */
    public function cancel(int $id, string $currentUserId): OvertimePayout {
        $payout = $this->mapper->find($id);

        if ($payout->isCancelled()) {
            return $payout;
        }

        $oldValues = $payout->jsonSerialize();
        $payout->setCancelledAt(new DateTime());
        $payout->setCancelledBy($currentUserId);
        $payout->setUpdatedAt(new DateTime());

        $result = $this->mapper->update($payout);

        $this->auditLogService->logUpdate(
            $currentUserId, 'overtime_payout', $result->getId(),
            $oldValues, $result->jsonSerialize()
        );

        return $result;
    }

    public function deleteByEmployeeId(int $employeeId): void {
        $this->mapper->deleteByEmployeeId($employeeId);
    }
}
