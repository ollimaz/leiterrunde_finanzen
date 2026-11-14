<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Module\IEventAccountingProvider;
use OCA\Leiterrunde\Module\IEventCatalog;
use OCA\Leiterrunde\Module\ModuleRegistry;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeFinanzen\Db\AccountMapper;
use OCA\LeiterrundeFinanzen\Db\BookMapper;
use OCA\LeiterrundeFinanzen\Db\ObligationMapper;
use OCA\LeiterrundeFinanzen\Db\Transaction;

class BookService implements IEventAccountingProvider {
	public function __construct(
		private PermissionService $perm,
		private ModuleRegistry $modules,
		private BookMapper $books,
		private AccountMapper $accounts,
		private ObligationMapper $obligations,
		private AuditService $audit,
	) {
	}

	public function options(): array {
		if (!$this->perm->has('finance.read') && !$this->perm->has('expenses.readAll')) {
			$this->perm->require('expenses.submitOwn');
		}
		$events = $this->modules->provider(IEventCatalog::class);
		return [
			'events' => $events !== null && $this->perm->has('events.read') ? $events->eventOptions() : [],
			'books' => array_map(static fn ($b) => $b->jsonSerialize(), $this->books->findAll()),
			'accounts' => array_map(static fn ($a) => ['id' => $a->getId(), 'name' => $a->getName()], $this->accounts->findAll(true)),
		];
	}

	public function requireEvent(?int $id): void {
		if ($id === null) { return; }
		if ($id <= 0) { throw new ApiException('Invalid event', 422); }
		$provider = $this->modules->provider(IEventCatalog::class);
		if ($provider === null) { throw new ApiException('Events module not active', 422); }
		$provider->requireEvent($id);
	}

	/** Resolve an event selection: an existing id, or a new event created from a title. */
	public function resolveEvent(?int $id, ?string $newTitle): ?int {
		$newTitle = $newTitle !== null ? trim($newTitle) : '';
		if ($newTitle === '') {
			$this->requireEvent($id);
			return $id;
		}
		if ($id !== null) {
			throw new ApiException('Select either an existing event or create a new one', 422);
		}
		$provider = $this->modules->provider(IEventCatalog::class);
		if ($provider === null) { throw new ApiException('Events module not active', 422); }
		return $provider->createEventOption($newTitle);
	}

	public function create(string $name, ?int $eventId, string $kind, ?int $accountId): array {
		$this->perm->require('finance.write');
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 200 || !in_array($kind, ['bank', 'cash'], true)
			|| ($kind === 'bank' && $accountId === null) || ($kind === 'cash' && $accountId !== null)) {
			throw new ApiException('Invalid accounting book', 422);
		}
		$this->requireEvent($eventId);
		if ($accountId !== null) { $this->requireAccount($accountId); }
		foreach ($this->books->findAll() as $book) {
			if ($kind === 'bank' && $book->getEventId() === $eventId && $book->getAccountId() === $accountId) {
				throw new ApiException('A bank book for this event and account already exists', 409);
			}
		}
		$book = $this->books->createBook($name, $eventId, $kind, $accountId);
		$this->audit->diff('book', $book->getId(), null, ['_action' => 'created']);
		return $book->jsonSerialize();
	}

	private function requireAccount(int $id): void {
		if ($id <= 0 || !$this->accounts->find($id)->getActive()) {
			throw new ApiException('Select an active source account', 422);
		}
	}

	public function expenseFields(?int $eventId, ?int $bookId, string $method, ?int $accountId, bool $requireBook = false): array {
		$this->requireEvent($eventId);
		if (!in_array($method, ['personal', 'card', 'transfer', 'cash'], true)) {
			throw new ApiException('Invalid payment method', 422);
		}
		if ($requireBook && $bookId === null) { throw new ApiException('Select an accounting book before booking a payment', 422); }
		$book = $bookId === null ? null : $this->books->find($bookId);
		if ($book !== null && $book->getEventId() !== $eventId) {
			throw new ApiException('The accounting book must belong to the selected event or general payments', 422);
		}
		if (in_array($method, ['card', 'transfer'], true)) {
			$accountId ??= $book?->getAccountId();
			if ($accountId === null || ($book !== null && ($book->getKind() !== 'bank' || $book->getAccountId() !== $accountId))) {
				throw new ApiException('Select the bank account used for this payment', 422);
			}
			$this->requireAccount($accountId);
		} elseif ($accountId !== null || ($method === 'cash' && $book !== null && $book->getKind() !== 'cash')) {
			throw new ApiException('Cash and personal advances must not specify an organization source account', 422);
		}
		return ['event_id' => $eventId, 'book_id' => $bookId, 'payment_method' => $method, 'source_account_id' => $accountId];
	}

	public function requireIncomingBook(Transaction $transaction, int $bookId): void {
		$book = $this->books->find($bookId);
		if ((float)$transaction->getAmount() <= 0 || $book->getKind() !== 'bank' || $book->getAccountId() !== $transaction->getAccountId()) {
			throw new ApiException('Incoming bank payments must use a book for their source account', 422);
		}
		$this->requireEvent($book->getEventId());
		if ($transaction->getObligationId() !== null
			&& $this->obligations->find($transaction->getObligationId())->getEventId() !== $book->getEventId()) {
			throw new ApiException('The payment and its obligation must belong to the same event', 422);
		}
	}

	public function summary(): array {
		$this->perm->require('finance.read');
		return $this->summarize(null, false);
	}

	public function eventAccounting(int $eventId): array {
		$this->perm->require('finance.read');
		return $this->summarize($eventId, true);
	}

	private function summarize(?int $eventId, bool $filter): array {
		$rows = [];
		$totals = ['income' => 0, 'outgoing' => 0, 'committed' => 0];
		foreach ($this->books->findAll() as $book) {
			if ($filter && $book->getEventId() !== $eventId) { continue; }
			$values = $this->books->totals($book->getId());
			$income = (int)round((float)$values['income'] * 100);
			$outgoing = (int)round((float)$values['outgoing'] * 100);
			foreach ($totals as $key => $_) { $totals[$key] += (int)round((float)$values[$key] * 100); }
			$rows[] = $book->jsonSerialize() + $values + ['balance' => number_format(($income - $outgoing) / 100, 2, '.', '')];
		}
		return ['books' => $rows, 'income' => $totals['income'] / 100, 'outgoing' => $totals['outgoing'] / 100,
			'committed' => $totals['committed'] / 100, 'balance' => ($totals['income'] - $totals['outgoing']) / 100];
	}
}
