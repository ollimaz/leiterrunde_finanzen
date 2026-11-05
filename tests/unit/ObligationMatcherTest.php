<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests\Unit;

use OCA\LeiterrundeFinanzen\Service\ObligationMatcher;
use PHPUnit\Framework\TestCase;

class ObligationMatcherTest extends TestCase {
	private ObligationMatcher $m;

	protected function setUp(): void {
		$this->m = new ObligationMatcher();
	}

	private function obl(int $id, string $ref, float $due, float $remaining): array {
		return ['id' => $id, 'reference' => $ref, 'amount_due' => $due, 'remaining' => $remaining];
	}

	public function testUniqueMatch(): void {
		$obls = [$this->obl(7, 'EVT1-REG3', 50.0, 50.0)];
		$this->assertSame(7, $this->m->match(50.0, 'Teilnahmebeitrag EVT1-REG3 Max', $obls));
	}

	public function testAmbiguousReturnsNull(): void {
		$obls = [
			$this->obl(1, 'EVT1-REG3', 50.0, 50.0),
			$this->obl(2, 'EVT1-REG3', 50.0, 50.0),
		];
		$this->assertNull($this->m->match(50.0, 'EVT1-REG3', $obls));
	}

	public function testDigitBoundaryGuard(): void {
		// REF3 must not match inside REF34
		$obls = [$this->obl(9, 'EVT1-REG3', 50.0, 50.0)];
		$this->assertNull($this->m->match(50.0, 'EVT1-REG34 bezahlt', $obls));
		// but REF34 matches
		$obls = [$this->obl(10, 'EVT1-REG34', 50.0, 50.0)];
		$this->assertSame(10, $this->m->match(50.0, 'EVT1-REG34 bezahlt', $obls));
	}

	public function testRemainingAmountMatch(): void {
		// due 100, already 60 assigned -> remaining 40
		$obls = [$this->obl(5, 'RE-2026-01', 100.0, 40.0)];
		$this->assertSame(5, $this->m->match(40.0, 'RE-2026-01', $obls));
		$this->assertNull($this->m->match(41.0, 'RE-2026-01', $obls));
	}

	public function testNoMatchOnMissingReference(): void {
		$obls = [$this->obl(1, '', 50.0, 50.0)];
		$this->assertNull($this->m->match(50.0, 'egal', $obls));
	}

	public function testNormalize(): void {
		$this->assertSame('EVT1REG3', ObligationMatcher::normalize('evt1-reg3'));
		$this->assertSame('ABC', ObligationMatcher::normalize(' a-b_c! '));
	}
}
