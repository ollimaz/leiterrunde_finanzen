<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\LeiterrundeFinanzen\Service\Import\Camt053Importer;
use PHPUnit\Framework\TestCase;

class Camt053ImporterTest extends TestCase {
	private const FIXTURE = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08">
 <BkToCstmrStmt><Stmt><Ntry>
   <Amt Ccy="EUR">50.00</Amt>
   <CdtDbtInd>CRDT</CdtDbtInd>
   <BookgDt><Dt>2026-11-01</Dt></BookgDt>
   <NtryDtls><TxDtls>
     <RltdPties><Dbtr><Nm>Max Mustermann</Nm></Dbtr></RltdPties>
     <RmtInf><Ustrd>EVT1-REG3</Ustrd></RmtInf>
   </TxDtls></NtryDtls>
 </Ntry>
 <Ntry>
   <Amt Ccy="EUR">12.34</Amt>
   <CdtDbtInd>DBIT</CdtDbtInd>
   <BookgDt><Dt>2026-11-02</Dt></BookgDt>
   <NtryDtls><TxDtls>
     <RltdPties><Cdtr><Nm>EDEKA</Nm></Cdtr></RltdPties>
     <RmtInf><Ustrd>Karte</Ustrd></RmtInf>
   </TxDtls></NtryDtls>
 </Ntry>
 </Stmt></BkToCstmrStmt>
</Document>
XML;

	public function testParse(): void {
		$rows = (new Camt053Importer())->parse(self::FIXTURE);
		$this->assertCount(2, $rows);
		$this->assertSame('2026-11-01', $rows[0]->bookingDate);
		$this->assertSame(50.0, $rows[0]->amount);
		$this->assertSame('EVT1-REG3', $rows[0]->purpose);
		$this->assertSame('Max Mustermann', $rows[0]->counterparty);
		$this->assertSame(-12.34, $rows[1]->amount);
		$this->assertSame('EDEKA', $rows[1]->counterparty);
	}

	public function testInvalidXmlReturnsEmpty(): void {
		$this->assertSame([], (new Camt053Importer())->parse('<xml><bad'));
	}

	public function testExternalEntityNotResolved(): void {
		$xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><r>&x;</r>';
		$this->assertSame([], (new Camt053Importer())->parse($xxe));
	}
}
