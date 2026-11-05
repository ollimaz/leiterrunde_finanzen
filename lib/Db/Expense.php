<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method string getSubmittedBy()
 * @method void setSubmittedBy(string $v)
 * @method string getExpenseDate()
 * @method void setExpenseDate(string $v)
 * @method string getAmount()
 * @method void setAmount(string $v)
 * @method ?string getCategory()
 * @method void setCategory(?string $v)
 * @method ?string getDescription()
 * @method void setDescription(?string $v)
 * @method ?int getFileId()
 * @method void setFileId(?int $v)
 * @method ?string getFileOwner()
 * @method void setFileOwner(?string $v)
 * @method ?string getFileName()
 * @method void setFileName(?string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?string getDecidedBy()
 * @method void setDecidedBy(?string $v)
 * @method ?int getDecidedAt()
 * @method void setDecidedAt(?int $v)
 * @method ?string getDecisionNote()
 * @method void setDecisionNote(?string $v)
 * @method ?int getPaidAt()
 * @method void setPaidAt(?int $v)
 * @method ?int getTransactionId()
 * @method void setTransactionId(?int $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Expense extends BaseEntity {
	protected int $personId = 0;
	protected string $submittedBy = '';
	protected string $expenseDate = '';
	protected string $amount = '0';
	protected ?string $category = null;
	protected ?string $description = null;
	protected ?int $fileId = null;
	protected ?string $fileOwner = null;
	protected ?string $fileName = null;
	protected string $status = 'pending';
	protected ?string $decidedBy = null;
	protected ?int $decidedAt = null;
	protected ?string $decisionNote = null;
	protected ?int $paidAt = null;
	protected ?int $transactionId = null;
	protected int $createdAt = 0;

	public function __construct() {
		foreach (['personId', 'fileId', 'decidedAt', 'paidAt', 'transactionId', 'createdAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
