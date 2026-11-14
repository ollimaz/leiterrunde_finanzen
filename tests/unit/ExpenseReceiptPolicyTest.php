<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\LeiterrundeFinanzen\Service\ExpenseReceiptPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpenseReceiptPolicyTest extends TestCase {
	#[DataProvider('missingReceipts')]
	public function testMissingReceiptBlocksApproval(?int $id, ?string $owner): void {
		$this->expectException(ApiException::class);
		ExpenseReceiptPolicy::requireReceipt($id, $owner);
	}

	public static function missingReceipts(): array {
		return [[null, null], [1, null], [null, 'trainer'], [0, 'trainer'], [-1, 'trainer'], [1, ' ']];
	}

	public function testStoredReceiptAllowsApproval(): void {
		ExpenseReceiptPolicy::requireReceipt(1, 'trainer');
		$this->addToAssertionCount(1);
	}

	#[DataProvider('lockedStatuses')]
	public function testDecidedExpensesCannotHaveReceiptReplaced(string $status): void {
		$this->expectException(ApiException::class);
		ExpenseReceiptPolicy::requirePending($status);
	}

	public static function lockedStatuses(): array {
		return [['approved'], ['paid'], ['rejected']];
	}

	public function testPendingExpenseCanReceiveReceiptLater(): void {
		ExpenseReceiptPolicy::requirePending('pending');
		$this->addToAssertionCount(1);
	}
}
