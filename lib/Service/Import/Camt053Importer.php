<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\Import;

use OCA\LeiterrundeFinanzen\Service\BankMail\ParsedTransaction;

/**
 * camt.053 XML-Import (namespaces via local-name(), XXE-sicher).
 */
class Camt053Importer {
	/** @return ParsedTransaction[] */
	public function parse(string $content): array {
		$doc = new \DOMDocument();
		$prev = libxml_use_internal_errors(true);
		$ok = $doc->loadXML($content, LIBXML_NONET | LIBXML_PARSEHUGE);
		libxml_use_internal_errors($prev);
		if (!$ok) {
			return [];
		}
		$xpath = new \DOMXPath($doc);
		$out = [];
		foreach ($xpath->query('//*[local-name()="Ntry"]') as $ntry) {
			$amt = $this->text($xpath, './*[local-name()="Amt"]', $ntry);
			$cdt = $this->text($xpath, './*[local-name()="CdtDbtInd"]', $ntry);
			$date = $this->text($xpath, './*[local-name()="BookgDt"]/*[local-name()="Dt"]', $ntry)
				?: $this->text($xpath, './*[local-name()="BookgDt"]/*[local-name()="DtTm"]', $ntry)
				?: $this->text($xpath, './*[local-name()="ValDt"]/*[local-name()="Dt"]', $ntry);
			if ($amt === null || !is_numeric($amt) || $date === null) {
				continue;
			}
			$amount = (float)$amt;
			if (mb_strtoupper($cdt ?? '') === 'DBIT') {
				$amount = -abs($amount);
			}
			$purpose = null;
			$ustrd = [];
			foreach ($xpath->query('.//*[local-name()="RmtInf"]/*[local-name()="Ustrd"]', $ntry) as $u) {
				$ustrd[] = $u->textContent;
			}
			if ($ustrd !== []) {
				$purpose = trim(implode(' ', $ustrd));
			}
			$cp = null;
			if (str_starts_with((string)$amount, '-')) {
				$cp = $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="Cdtr"]/*[local-name()="Nm"]', $ntry)
					?: $this->text($xpath, './/*[local-name()="Cdtr"]/*[local-name()="Nm"]', $ntry);
			} else {
				$cp = $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="Dbtr"]/*[local-name()="Nm"]', $ntry)
					?: $this->text($xpath, './/*[local-name()="Dbtr"]/*[local-name()="Nm"]', $ntry);
			}
			$out[] = new ParsedTransaction(
				substr($date, 0, 10),
				$amount,
				$cp !== null && $cp !== '' ? $cp : null,
				$purpose !== '' ? $purpose : null,
			);
		}
		return $out;
	}

	private function text(\DOMXPath $xpath, string $expr, \DOMNode $ctx): ?string {
		$node = $xpath->query($expr, $ctx)->item(0);
		return $node === null ? null : trim($node->textContent);
	}
}
