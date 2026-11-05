<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method ?string getPayerName()
 * @method void setPayerName(?string $v)
 * @method string getLabel()
 * @method void setLabel(string $v)
 * @method string getAmountDue()
 * @method void setAmountDue(string $v)
 * @method ?string getDueDate()
 * @method void setDueDate(?string $v)
 * @method ?string getReference()
 * @method void setReference(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?int getPersonId()
 * @method void setPersonId(?int $v)
 * @method ?int getRegistrationId()
 * @method void setRegistrationId(?int $v)
 * @method ?string getNotes()
 * @method void setNotes(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Obligation extends BaseEntity {
	protected ?string $payerName = null;
	protected string $label = '';
	protected string $amountDue = '0';
	protected ?string $dueDate = null;
	protected ?string $reference = null;
	protected string $status = 'open';
	protected ?int $personId = null;
	protected ?int $registrationId = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('personId', 'integer');
		$this->addType('registrationId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
}
