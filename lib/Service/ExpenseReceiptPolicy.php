<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\Leiterrunde\Exception\ApiException;

class ExpenseReceiptPolicy {
	public static function requireReceipt(?int $fileId, ?string $owner): void {
		if ($fileId === null || $fileId <= 0 || $owner === null || trim($owner) === '') {
			throw new ApiException('Upload a receipt before approving or booking this expense', 422);
		}
	}

	public static function requirePending(string $status): void {
		if ($status !== 'pending') {
			throw new ApiException('Only pending expenses can be changed; reopen the expense for review first', 409);
		}
	}
}
