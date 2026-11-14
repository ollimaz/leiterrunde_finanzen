<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

class Book extends BaseEntity {
	protected string $name = '';
	protected ?int $eventId = null;
	protected string $kind = '';
	protected ?int $accountId = null;
	protected ?string $bankKey = null;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('accountId', 'integer');
	}
}
