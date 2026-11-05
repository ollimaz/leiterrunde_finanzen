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

class Version1000Date20261105000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_accounts')) {
			$table = $schema->createTable('lr_accounts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('iban', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('parser_key', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('mail_hint', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->setPrimaryKey(['id']);
		}


		if (!$schema->hasTable('lr_transactions')) {
			$table = $schema->createTable('lr_transactions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('account_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('booking_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('counterparty', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('purpose', Types::TEXT, ['notnull' => false]);
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 20]);
			$table->addColumn('dedupe_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('obligation_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('raw', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['dedupe_hash'], 'lr_tx_dedupe_uniq');
			$table->addIndex(['account_id'], 'lr_tx_acc_idx');
			$table->addIndex(['obligation_id'], 'lr_tx_obl_idx');
		}


		if (!$schema->hasTable('lr_obligations')) {
			$table = $schema->createTable('lr_obligations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('payer_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('amount_due', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('due_date', Types::DATE, ['notnull' => false]);
			$table->addColumn('reference', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'open']);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('registration_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['reference'], 'lr_obl_ref_uniq');
			$table->addIndex(['status'], 'lr_obl_status_idx');
		}


		if (!$schema->hasTable('lr_expenses')) {
			$table = $schema->createTable('lr_expenses');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('submitted_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('expense_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 100]);
			$table->addColumn('description', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('file_owner', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'pending']);
			$table->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('decided_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('decision_note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('paid_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('transaction_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['person_id'], 'lr_exp_person_idx');
			$table->addIndex(['status'], 'lr_exp_status_idx');
		}


		return $schema;
	}
}
