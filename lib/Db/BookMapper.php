<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class BookMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_books', Book::class);
	}

	public function find(int $id): Book {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('name', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function createBook(string $name, ?int $eventId, string $kind, ?int $accountId): Book {
		$book = new Book();
		$book->setName($name);
		$book->setEventId($eventId);
		$book->setKind($kind);
		$book->setAccountId($accountId);
		$book->setBankKey($kind === 'bank' ? 'bank:' . ($eventId ?? 0) . ':' . $accountId : null);
		return $this->insert($book);
	}

	public function totals(int $bookId): array {
		$income = $this->db->getQueryBuilder();
		$income->select($income->func()->sum('amount'))->from('lr_transactions')
			->where($income->expr()->eq('book_id', $income->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->andWhere($income->expr()->gt('amount', $income->createNamedParameter('0')));
		$result = ['income' => (string)($income->executeQuery()->fetchOne() ?: '0')];
		foreach (['paid' => 'outgoing', 'approved' => 'committed'] as $status => $key) {
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->sum('amount'))->from('lr_expenses')
				->where($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
			$result[$key] = (string)($qb->executeQuery()->fetchOne() ?: '0');
		}
		return $result;
	}
}
