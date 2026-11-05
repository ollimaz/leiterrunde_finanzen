<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

class MailMessage {
	public function __construct(
		public readonly string $from = '',
		public readonly string $subject = '',
		public readonly string $body = '',
	) {
	}
}
