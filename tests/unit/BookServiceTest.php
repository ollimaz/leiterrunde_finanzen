<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\LeiterrundeFinanzen\Db\Book;
use OCA\LeiterrundeFinanzen\Db\Obligation;
use OCA\LeiterrundeFinanzen\Db\Transaction;
use OCA\LeiterrundeFinanzen\Service\BookService;
use PHPUnit\Framework\TestCase;

class BookServiceTest extends TestCase {
	private function service(?Book $book = null, ?Obligation $obligation = null): BookService {
		$perm = $this->createMock(\OCA\Leiterrunde\Service\PermissionService::class);
		$modules = $this->createMock(\OCA\Leiterrunde\Module\ModuleRegistry::class);
		$books = $this->createMock(\OCA\LeiterrundeFinanzen\Db\BookMapper::class);
		$books->method('find')->willReturnCallback(static function () use ($book) {
			if ($book === null) {
				throw new \OCP\AppFramework\Db\DoesNotExistException('nope');
			}
			return $book;
		});
		$accounts = $this->createMock(\OCA\LeiterrundeFinanzen\Db\AccountMapper::class);
		$account = new \OCA\LeiterrundeFinanzen\Db\Account();
		$account->setActive(true);
		$accounts->method('find')->willReturn($account);
		$obligations = $this->createMock(\OCA\LeiterrundeFinanzen\Db\ObligationMapper::class);
		$obligations->method('find')->willReturnCallback(static function () use ($obligation) {
			return $obligation ?? new Obligation();
		});
		$audit = $this->createMock(\OCA\Leiterrunde\Service\AuditService::class);
		return new BookService($perm, $modules, $books, $accounts, $obligations, $audit);
	}

	private function book(string $kind, ?int $accountId = null, ?int $eventId = null): Book {
		$b = new Book();
		$b->setKind($kind);
		$b->setAccountId($accountId);
		$b->setEventId($eventId);
		return $b;
	}

	public function testPersonalAdvanceWithoutBook(): void {
		$out = $this->service()->expenseFields(null, null, 'personal', null);
		$this->assertSame(['event_id' => null, 'book_id' => null, 'payment_method' => 'personal', 'source_account_id' => null], $out);
	}

	public function testInvalidPaymentMethodRejected(): void {
		$this->expectException(ApiException::class);
		$this->service()->expenseFields(null, null, 'gold', null);
	}

	public function testCardPaymentNeedsBankAccount(): void {
		$this->expectException(ApiException::class);
		$this->service()->expenseFields(null, null, 'card', null);
	}

	public function testCardPaymentDerivesAccountFromBankBook(): void {
		$out = $this->service($this->book('bank', 7))->expenseFields(null, 5, 'card', null);
		$this->assertSame(7, $out['source_account_id']);
	}

	public function testCashExpenseCannotUseBankBook(): void {
		$this->expectException(ApiException::class);
		$this->service($this->book('bank', 7))->expenseFields(null, 5, 'cash', null);
	}

	public function testCashExpenseCannotNameSourceAccount(): void {
		$this->expectException(ApiException::class);
		$this->service()->expenseFields(null, null, 'cash', 7);
	}

	public function testBookMustMatchSelectedEvent(): void {
		$this->expectException(ApiException::class);
		$this->service($this->book('cash', null, 3))->expenseFields(null, 5, 'cash', null);
	}

	public function testIncomingTransactionBooksToMatchingBankBook(): void {
		$tx = new Transaction();
		$tx->setAccountId(7);
		$tx->setAmount('12.50');
		$this->service($this->book('bank', 7))->requireIncomingBook($tx, 5);
		$this->addToAssertionCount(1);
	}

	public function testIncomingTransactionRejectsForeignAccountBook(): void {
		$tx = new Transaction();
		$tx->setAccountId(7);
		$tx->setAmount('12.50');
		$this->expectException(ApiException::class);
		$this->service($this->book('bank', 9))->requireIncomingBook($tx, 5);
	}

	public function testIncomingTransactionRejectsCashBook(): void {
		$tx = new Transaction();
		$tx->setAccountId(7);
		$tx->setAmount('12.50');
		$this->expectException(ApiException::class);
		$this->service($this->book('cash'))->requireIncomingBook($tx, 5);
	}

	public function testIncomingTransactionAndObligationEventsMustMatch(): void {
		$tx = new Transaction();
		$tx->setAccountId(7);
		$tx->setAmount('12.50');
		$tx->setObligationId(11);
		$obligation = new Obligation();
		$obligation->setEventId(4);
		$this->expectException(ApiException::class);
		$this->service($this->book('bank', 7, 3), $obligation)->requireIncomingBook($tx, 5);
	}
}
