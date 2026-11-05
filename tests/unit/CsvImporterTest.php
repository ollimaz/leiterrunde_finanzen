<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\LeiterrundeFinanzen\Service\Import\CsvImporter;
use PHPUnit\Framework\TestCase;

class CsvImporterTest extends TestCase {
	public function testGermanCsvSemicolon(): void {
		$csv = "Buchungstag;Betrag;Verwendungszweck;Beguenstigter/Zahlungspflichtiger\n"
			. "01.11.2026;50,00;EVT1-REG3;Max Mustermann\n"
			. "02.11.2026;-12,34;Material;EDEKA\n";
		$rows = (new CsvImporter())->parse($csv);
		$this->assertCount(2, $rows);
		$this->assertSame('2026-11-01', $rows[0]->bookingDate);
		$this->assertSame(50.0, $rows[0]->amount);
		$this->assertSame('EVT1-REG3', $rows[0]->purpose);
		$this->assertSame('Max Mustermann', $rows[0]->counterparty);
		$this->assertSame(-12.34, $rows[1]->amount);
	}

	public function testThousandsAndNegative(): void {
		$csv = "Datum;Umsatz\n05.11.2026;1.234,56\n05.11.2026;-1.234,56\n";
		$rows = (new CsvImporter())->parse($csv);
		$this->assertSame(1234.56, $rows[0]->amount);
		$this->assertSame(-1234.56, $rows[1]->amount);
	}

	public function testCommaDelimiter(): void {
		$csv = "Buchungsdatum,Betrag,Verwendungszweck\n01.11.2026,\"10,50\",Test\n";
		$rows = (new CsvImporter())->parse($csv);
		$this->assertCount(1, $rows);
		$this->assertSame(10.5, $rows[0]->amount);
	}

	public function testUnparseableReturnsEmpty(): void {
		$this->assertSame([], (new CsvImporter())->parse("irgendwas\nanderes"));
	}
}
