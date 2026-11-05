<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

class ParserRegistry {
	/** @var array<string,BankMailParser> */
	private array $parsers = [];

	public function __construct() {
		foreach ([new GenericParser(), new SparkasseParser()] as $p) {
			$this->parsers[$p->key()] = $p;
		}
	}

	public function get(string $key): ?BankMailParser {
		return $this->parsers[$key] ?? null;
	}

	/** @return array<string,string> key => label */
	public function all(): array {
		$out = [];
		foreach ($this->parsers as $key => $p) {
			$out[$key] = $p->label();
		}
		return $out;
	}
}
