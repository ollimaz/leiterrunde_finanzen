<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

class ParsedTransaction {
	public function __construct(
		public readonly string $bookingDate,
		public readonly float $amount,
		public readonly ?string $counterparty = null,
		public readonly ?string $purpose = null,
		public readonly ?string $raw = null,
	) {
	}
}
