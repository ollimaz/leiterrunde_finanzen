<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261110000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('lr_books')) {
			$table = $schema->createTable('lr_books');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('bank_key', Types::STRING, ['notnull' => false, 'length' => 80]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'lr_book_event_idx');
			$table->addUniqueIndex(['bank_key'], 'lr_book_bank_key_idx');
		}
		foreach (['lr_expenses', 'lr_obligations'] as $name) {
			$table = $schema->getTable($name);
			if (!$table->hasColumn('event_id')) {
				$table->addColumn('event_id', Types::BIGINT, ['notnull' => false]);
				$table->addIndex(['event_id'], $name === 'lr_expenses' ? 'lr_exp_event_idx' : 'lr_obl_event_idx');
			}
		}
		$expenses = $schema->getTable('lr_expenses');
		if (!$expenses->hasColumn('book_id')) {
			$expenses->addColumn('book_id', Types::BIGINT, ['notnull' => false]);
			$expenses->addColumn('payment_method', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'personal']);
			$expenses->addColumn('source_account_id', Types::BIGINT, ['notnull' => false]);
			$expenses->addIndex(['book_id'], 'lr_exp_book_idx');
		}
		$transactions = $schema->getTable('lr_transactions');
		if (!$transactions->hasColumn('book_id')) {
			$transactions->addColumn('book_id', Types::BIGINT, ['notnull' => false]);
			$transactions->addIndex(['book_id'], 'lr_tx_book_idx');
		}
		return $schema;
	}
}
