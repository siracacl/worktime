<?php

/**
 * SPDX-FileCopyrightText: 2026 Axel Deffner <axel@cpcmomentum.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\WorkTime\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create wt_overtime_payouts table for paid-out overtime that reduces the
 * running flextime balance (Gleitzeitkonto) from a given effective month.
 */
class Version000015Date20260606000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('wt_overtime_payouts')) {
			$table = $schema->createTable('wt_overtime_payouts');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('employee_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('year', Types::INTEGER, [
				'notnull' => true,
			]);
			// Effective month (1-12): the payout reduces the balance from this month on.
			$table->addColumn('month', Types::INTEGER, [
				'notnull' => true,
			]);
			// Paid-out minutes (positive value reduces the flextime balance).
			$table->addColumn('minutes', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('note', Types::STRING, [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('cancelled_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('cancelled_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			// Non-unique: multiple payouts per employee and year are allowed.
			$table->addIndex(['employee_id', 'year'], 'wt_payout_emp_year_idx');
		}

		return $schema;
	}
}
