<?php

/**
 * SPDX-FileCopyrightText: 2026 Axel Deffner <axel@cpcmomentum.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\WorkTime\Controller;

use OCA\WorkTime\Service\OvertimePayoutService;
use OCA\WorkTime\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class OvertimePayoutController extends BaseController {

    public function __construct(
        IRequest $request,
        ?string $userId,
        private OvertimePayoutService $payoutService,
        private PermissionService $permissionService,
    ) {
        parent::__construct($request, $userId);
    }

    /**
     * List active overtime payouts for an employee and year.
     */
    #[NoAdminRequired]
    public function index(int $employeeId, int $year): JSONResponse {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }

        $ownEmployee = $this->permissionService->getEmployeeForUser($this->userId);
        $ownEmployeeId = $ownEmployee?->getId();
        if ($employeeId !== $ownEmployeeId && !$this->permissionService->canViewEmployee($this->userId, $employeeId)) {
            return $this->forbiddenResponse();
        }

        $payouts = $this->payoutService->findActiveByEmployeeAndYear($employeeId, $year);
        return $this->successResponse($payouts);
    }

    /**
     * Record a paid-out chunk of overtime (reduces the flextime balance).
     */
    #[NoAdminRequired]
    public function create(
        int $employeeId,
        int $year,
        int $month,
        int $minutes,
        string $note = '',
    ): JSONResponse {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }

        if (!$this->permissionService->canManageEmployees($this->userId)) {
            return $this->forbiddenResponse();
        }

        if ($month < 1 || $month > 12) {
            return new JSONResponse(['error' => 'Invalid month.'], Http::STATUS_BAD_REQUEST);
        }

        if ($minutes === 0) {
            return new JSONResponse(['error' => 'Amount must not be zero.'], Http::STATUS_BAD_REQUEST);
        }

        if (empty(trim($note))) {
            return new JSONResponse(['error' => 'Reason required.'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $payout = $this->payoutService->create(
                $employeeId, $year, $month, $minutes, $note, $this->userId
            );
            return $this->successResponse($payout);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Cancel (soft-delete) a payout.
     */
    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        if ($authError = $this->requireAuth()) {
            return $authError;
        }

        if (!$this->permissionService->canManageEmployees($this->userId)) {
            return $this->forbiddenResponse();
        }

        try {
            $payout = $this->payoutService->cancel($id, $this->userId);
            return $this->successResponse($payout);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
