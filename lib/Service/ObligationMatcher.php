<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

/**
 * Reine Auto-Match-Logik: Zahlung -> Forderung.
 */
class ObligationMatcher {
	/**
	 * @param float $amount Transaktionsbetrag (> 0)
	 * @param string $purpose Verwendungszweck
	 * @param array $obligations [ ['id'=>int,'reference'=>string,'amount_due'=>float,'remaining'=>float], ... ]
	 * @return ?int obligation id, wenn genau ein Kandidat passt
	 */
	public function match(float $amount, string $purpose, array $obligations): ?int {
		$normPurpose = self::normalize($purpose);
		$candidates = [];
		foreach ($obligations as $o) {
			$ref = self::normalize((string)($o['reference'] ?? ''));
			if ($ref === '') {
				continue;
			}
			$dueCents = (int)round(((float)$o['amount_due']) * 100);
			$remCents = (int)round(((float)$o['remaining']) * 100);
			$amtCents = (int)round($amount * 100);
			if ($amtCents !== $dueCents && $amtCents !== $remCents) {
				continue;
			}
			$pos = strpos($normPurpose, $ref);
			if ($pos === false) {
				continue;
			}
			// Boundary guard: referenzen, die auf eine Ziffer enden, dürfen nicht
			// direkt of einer weiteren Ziffer gefolgt werden (EVT1-REG3 != EVT1-REG34)
			if (ctype_digit(substr($ref, -1))) {
				$next = substr($normPurpose, $pos + strlen($ref), 1);
				if ($next !== '' && ctype_digit($next)) {
					continue;
				}
			}
			$candidates[] = (int)$o['id'];
		}
		return count($candidates) === 1 ? $candidates[0] : null;
	}

	public static function normalize(string $s): string {
		return (string)preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($s));
	}
}
