<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\LeiterrundeFinanzen\Service\Imap\MimeParser;
use PHPUnit\Framework\TestCase;

class MimeParserTest extends TestCase {
	public function testPlainUtf8(): void {
		$raw = "From: bank@x.de\r\nSubject: Konto\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nBetrag: 50,00 EUR\n";
		$m = (new MimeParser())->parse($raw);
		$this->assertSame('bank@x.de', $m->from);
		$this->assertSame('Konto', $m->subject);
		$this->assertStringContainsString('50,00', $m->body);
	}

	public function testQuotedPrintable(): void {
		$raw = "From: =?UTF-8?Q?Sparkasse?= <b@x.de>\r\nSubject: =?UTF-8?Q?Umsatz_mit_L=C3=A4nge?=\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nBetrag:=20=C3=9C50=2C00=20EUR=0A";
		$m = (new MimeParser())->parse($raw);
		$this->assertStringContainsString('Sparkasse', $m->from);
		$this->assertSame('Umsatz mit Länge', $m->subject);
		$this->assertStringContainsString('Ü50,00 EUR', $m->body);
	}

	public function testBase64AndLatin1(): void {
		$body = base64_encode("Betrag: 12,00 EUR");
		$raw = "From: b@x\r\nContent-Type: text/plain; charset=iso-8859-1\r\nContent-Transfer-Encoding: base64\r\n\r\n$body";
		$m = (new MimeParser())->parse($raw);
		$this->assertStringContainsString('12,00', $m->body);
	}

	public function testMultipartAlternativePrefersPlain(): void {
		$raw = "From: b@x\r\nContent-Type: multipart/alternative; boundary=\"BND\"\r\n\r\n"
			. "--BND\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nKLARTEXT Version\r\n"
			. "--BND\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<b>HTML Version</b>\r\n"
			. "--BND--\r\n";
		$m = (new MimeParser())->parse($raw);
		$this->assertStringContainsString('KLARTEXT', $m->body);
		$this->assertStringNotContainsString('HTML Version', $m->body);
	}

	public function testMultipartHtmlOnlyStripped(): void {
		$raw = "From: b@x\r\nContent-Type: multipart/alternative; boundary=B\r\n\r\n"
			. "--B\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>Betrag: <b>7,00</b> EUR</p>\r\n--B--\r\n";
		$m = (new MimeParser())->parse($raw);
		$this->assertStringContainsString('7,00', $m->body);
		$this->assertStringNotContainsString('<b>', $m->body);
	}

	public function testHeaderUnfolding(): void {
		$raw = "From: b@x\r\nSubject: Langer\r\n\tBetreff Teil\r\n\r\ntext";
		$m = (new MimeParser())->parse($raw);
		$this->assertSame('Langer Betreff Teil', $m->subject);
	}
}
