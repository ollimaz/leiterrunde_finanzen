<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

interface BankMailParser {
	public function key(): string;

	public function label(): string;

	/** @return ParsedTransaction[] leeres Array = nicht zutreffend */
	public function parse(MailMessage $message): array;
}
