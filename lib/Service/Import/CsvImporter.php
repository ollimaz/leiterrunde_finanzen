<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\Import;

use OCA\LeiterrundeFinanzen\Service\BankMail\ParsedTransaction;

/**
 * Import deutscher Bank-CSVs (Header-Heuristik, ; oder ,, deutsches Dezimalformat).
 */
class CsvImporter {
	private const DATE_COLS = ['buchungstag', 'buchungsdatum', 'datum', 'valuta', 'wertstellung'];
	private const AMOUNT_COLS = ['betrag', 'umsatz', 'betrag in eur', 'betrag (eur)'];
	private const PURPOSE_COLS = ['verwendungszweck', 'buchungstext', 'vorgang'];
	private const CP_COLS = ['beguenstigter/zahlungspflichtiger', 'begünstigter/zahlungspflichtiger',
		'name zahlungsbeteiligter', 'auftraggeber/empfänger', 'auftraggeber/empfaenger',
		'auftraggeber', 'empfänger', 'empfaenger', 'begünstigter', 'beguenstigter', 'name'];

	/** @return ParsedTransaction[] */
	public function parse(string $content): array {
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$firstLine = strtok($content, "\r\n") ?: '';
		$delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
		$fp = fopen('php://temp', 'r+');
		fwrite($fp, $content);
		rewind($fp);
		$header = fgetcsv($fp, 0, $delim, escape: '');
		if ($header === false) {
			fclose($fp);
			return [];
		}
		$cols = array_map(static fn ($c) => mb_strtolower(trim((string)$c)), $header);
		$dateIdx = $this->findCol($cols, self::DATE_COLS);
		$amountIdx = $this->findCol($cols, self::AMOUNT_COLS);
		$purposeIdx = $this->findCol($cols, self::PURPOSE_COLS);
		$cpIdx = $this->findCol($cols, self::CP_COLS);
		if ($dateIdx === null || $amountIdx === null) {
			fclose($fp);
			return [];
		}
		$out = [];
		while (($row = fgetcsv($fp, 0, $delim, escape: '')) !== false) {
			if ($row === [null]) {
				continue;
			}
			$date = $this->parseDate((string)($row[$dateIdx] ?? ''));
			$amount = $this->parseGermanNumber((string)($row[$amountIdx] ?? ''));
			if ($date === null || $amount === null) {
				continue;
			}
			$out[] = new ParsedTransaction(
				$date,
				$amount,
				$cpIdx !== null ? trim((string)($row[$cpIdx] ?? '')) ?: null : null,
				$purposeIdx !== null ? trim((string)($row[$purposeIdx] ?? '')) ?: null : null,
			);
		}
		fclose($fp);
		return $out;
	}

	private function findCol(array $cols, array $candidates): ?int {
		foreach ($cols as $i => $name) {
			foreach ($candidates as $c) {
				if (str_starts_with($name, $c)) {
					return $i;
				}
			}
		}
		return null;
	}

	private function parseDate(string $s): ?string {
		$s = trim($s);
		foreach (['!d.m.Y', '!Y-m-d', '!d.m.y'] as $fmt) {
			$d = \DateTimeImmutable::createFromFormat($fmt, $s);
			if ($d !== false) {
				return $d->format('Y-m-d');
			}
		}
		return null;
	}

	private function parseGermanNumber(string $s): ?float {
		$s = trim($s);
		if ($s === '') {
			return null;
		}
		$negative = str_starts_with($s, '-') || str_ends_with($s, '-');
		$s = str_replace(['-', '+', ' '], '', $s);
		if (str_contains($s, ',') && str_contains($s, '.')) {
			$s = str_replace('.', '', $s); // 1.234,56
		}
		$s = str_replace(',', '.', $s);
		if (!is_numeric($s)) {
			return null;
		}
		$v = (float)$s;
		return $negative ? -abs($v) : $v;
	}
}
