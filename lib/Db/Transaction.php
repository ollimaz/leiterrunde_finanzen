<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method int getAccountId()
 * @method void setAccountId(int $v)
 * @method string getBookingDate()
 * @method void setBookingDate(string $v)
 * @method string getAmount()
 * @method void setAmount(string $v)
 * @method ?string getCounterparty()
 * @method void setCounterparty(?string $v)
 * @method ?string getPurpose()
 * @method void setPurpose(?string $v)
 * @method string getSource()
 * @method void setSource(string $v)
 * @method string getDedupeHash()
 * @method void setDedupeHash(string $v)
 * @method ?int getObligationId()
 * @method void setObligationId(?int $v)
 * @method ?string getRaw()
 * @method void setRaw(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Transaction extends BaseEntity {
	protected int $accountId = 0;
	protected string $bookingDate = '';
	protected string $amount = '0';
	protected ?string $counterparty = null;
	protected ?string $purpose = null;
	protected string $source = 'manual';
	protected string $dedupeHash = '';
	protected ?int $obligationId = null;
	protected ?string $raw = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('accountId', 'integer');
		$this->addType('obligationId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
