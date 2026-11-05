<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests;

use OCA\LeiterrundeFinanzen\Service\ImapStatus;
use PHPUnit\Framework\TestCase;

class ImapStatusTest extends TestCase {
	public function testEncodeParsesJson(): void {
		$raw = ImapStatus::encode(true, 1700000000, ['fetched' => 3, 'imported' => 1, 'duplicates' => 1, 'skipped' => 0, 'errors' => 1]);
		$st = ImapStatus::parse($raw);
		$this->assertTrue($st['ok']);
		$this->assertSame(1700000000, $st['ts']);
		$this->assertSame(3, $st['mails']);
		$this->assertSame(1, $st['errors']);
	}

	public function testEncodeErrorMessageFails(): void {
		$st = ImapStatus::parse(ImapStatus::encode(false, 1700000000, [], 'login failed'));
		$this->assertFalse($st['ok']);
		$this->assertSame('login failed', $st['message']);
	}

	public function testLegacyErrorString(): void {
		$st = ImapStatus::parse('2026-11-05 10:50: Fehler: Verbindung fehlgeschlagen');
		$this->assertFalse($st['ok']);
		$this->assertSame((int)strtotime('2026-11-05 10:50'), $st['ts']);
		$this->assertSame(1, $st['errors']);
		$this->assertSame('Fehler: Verbindung fehlgeschlagen', $st['raw']);
	}

	public function testLegacySuccessString(): void {
		$st = ImapStatus::parse('2026-11-05 10:50: Letzter Abruf: 2 Mails, 1 importiert, 0 Duplikate, 1 übersprungen, 0 Fehler');
		$this->assertTrue($st['ok']);
		$this->assertSame((int)strtotime('2026-11-05 10:50'), $st['ts']);
	}

	public function testEmpty(): void {
		$this->assertNull(ImapStatus::parse(''));
		$this->assertNull(ImapStatus::parse(null));
	}
}
