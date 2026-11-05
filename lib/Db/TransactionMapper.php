<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<Transaction> */
class TransactionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_transactions', Transaction::class);
	}

	public function find(int $id): Transaction {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findByHash(string $hash): ?Transaction {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('dedupe_hash', $qb->createNamedParameter($hash)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return Transaction[] */
	public function findFiltered(?int $accountId = null, ?bool $unassigned = null, int $limit = 500): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('booking_date', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit);
		if ($accountId !== null) {
			$qb->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)));
		}
		if ($unassigned === true) {
			$qb->andWhere($qb->expr()->isNull('obligation_id'));
		}
		return $this->findEntities($qb);
	}

	/** @return Transaction[] */
	public function findForObligation(int $obligationId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('obligation_id', $qb->createNamedParameter($obligationId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}
}
