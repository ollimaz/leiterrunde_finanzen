<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method string getName()
 * @method void setName(string $v)
 * @method ?string getIban()
 * @method void setIban(?string $v)
 * @method ?string getParserKey()
 * @method void setParserKey(?string $v)
 * @method ?string getMailHint()
 * @method void setMailHint(?string $v)
 * @method bool getActive()
 * @method void setActive(bool $v)
 */
class Account extends BaseEntity {
	protected string $name = '';
	protected ?string $iban = null;
	protected ?string $parserKey = null;
	protected ?string $mailHint = null;
	protected bool $active = true;

	public function __construct() {
		$this->addType('active', 'boolean');
	}
}
