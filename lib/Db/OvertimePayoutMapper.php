<?php

/**
 * SPDX-FileCopyrightText: 2026 Axel Deffner <axel@cpcmomentum.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\WorkTime\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<OvertimePayout>
 */
class OvertimePayoutMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'wt_overtime_payouts', OvertimePayout::class);
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): OvertimePayout {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Active (not cancelled) payouts for an employee and year, oldest month first.
     *
     * @return OvertimePayout[]
     */
    public function findActiveByEmployeeAndYear(int $employeeId, int $year): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('cancelled_at'))
            ->orderBy('month', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Active payouts for several employees in a year (batch load for team views).
     *
     * @param int[] $employeeIds
     * @return OvertimePayout[]
     */
    public function findActiveByEmployeeIdsAndYear(array $employeeIds, int $year): array {
        if (empty($employeeIds)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->in('employee_id', $qb->createNamedParameter($employeeIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('cancelled_at'))
            ->orderBy('month', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Sum of active paid-out minutes for an employee and year, optionally only up
     * to and including a given month.
     */
    public function sumMinutes(int $employeeId, int $year, int $uptoMonth = 12): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->sum('minutes'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->lte('month', $qb->createNamedParameter($uptoMonth, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('cancelled_at'));

        $result = $qb->executeQuery();
        $sum = $result->fetchOne();
        $result->closeCursor();

        return (int)$sum;
    }

    public function deleteByEmployeeId(int $employeeId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
