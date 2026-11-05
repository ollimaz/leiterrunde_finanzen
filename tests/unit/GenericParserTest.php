<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\LeiterrundeFinanzen\Service\BankMail\GenericParser;
use OCA\LeiterrundeFinanzen\Service\BankMail\MailMessage;
use PHPUnit\Framework\TestCase;

class GenericParserTest extends TestCase {
	public function testCredit(): void {
		$body = "Gutschrift auf Ihr Konto\nBetrag: 50,00 EUR\nDatum: 01.11.2026\nAuftraggeber: Max Mustermann\nVerwendungszweck: EVT1-REG3\n";
		$rows = (new GenericParser())->parse(new MailMessage('bank@x.de', 'Kontoumsatz', $body));
		$this->assertCount(1, $rows);
		$this->assertSame(50.0, $rows[0]->amount);
		$this->assertSame('2026-11-01', $rows[0]->bookingDate);
		$this->assertSame('Max Mustermann', $rows[0]->counterparty);
		$this->assertSame('EVT1-REG3', $rows[0]->purpose);
	}

	public function testDebitNegative(): void {
		$body = "Lastschrift\nBetrag: 12,34 EUR\nDatum: 02.11.2026\nZahlungspflichtiger: Sie\nEmpfänger: EDEKA\n";
		$rows = (new GenericParser())->parse(new MailMessage('b@x', '', $body));
		$this->assertSame(-12.34, $rows[0]->amount);
	}

	public function testNotApplicableReturnsEmpty(): void {
		$this->assertSame([], (new GenericParser())->parse(new MailMessage('a', 'b', 'Newsletter ohne Betrag')));
	}
}
