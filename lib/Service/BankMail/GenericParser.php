<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

/**
 * Generischer Parser für deutsche Bank-Push-Mails (Betrag/Datum/Auftraggeber/Verwendungszweck).
 */
class GenericParser implements BankMailParser {
	public function key(): string {
		return 'generic';
	}

	public function label(): string {
		return 'Generisch (deutsche Bankmail)';
	}

	public function parse(MailMessage $message): array {
		$body = $message->body;
		if (preg_match('/(?:Betrag|Umsatz)\s*:?\s*([+-]?[\d.]+,\d{2})\s*(?:EUR|Euro|€)/iu', $body, $mAmount) !== 1) {
			return [];
		}
		$amount = $this->parseGermanNumber($mAmount[1]);
		if (preg_match('/Gutschrift|Eingang|Guthaben/iu', $body) === 1) {
			if ($amount < 0) {
				$amount = -$amount;
			}
		} elseif (preg_match('/Lastschrift|Belastung|Abbuchung|Ausgang/iu', $body) === 1) {
			if ($amount >= 0) {
				$amount = -$amount;
			}
		}
		$date = null;
		if (preg_match('/(?:Datum|Buchungsdatum|Valuta|Wertstellung)\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{4})/iu', $body, $mDate) === 1
			|| preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{4})\b/', $body, $mDate) === 1) {
			$d = \DateTimeImmutable::createFromFormat('!d.m.Y', $mDate[1]);
			if ($d !== false) {
				$date = $d->format('Y-m-d');
			}
		}
		$counterparty = null;
		if (preg_match('/(?:Auftraggeber|Empfänger|Zahlungspflichtiger|Begünstigter)\s*:?\s*(.+)/iu', $body, $mCp) === 1) {
			$counterparty = trim(preg_split('/[\r\n]/', $mCp[1])[0] ?? '');
		}
		$purpose = null;
		if (preg_match('/Verwendungszweck\s*:?\s*(.+)/iu', $body, $mP) === 1) {
			$purpose = trim(preg_split('/[\r\n]/', $mP[1])[0] ?? '');
		}
		return [new ParsedTransaction(
			$date ?? date('Y-m-d'),
			$amount,
			$counterparty !== '' ? $counterparty : null,
			$purpose !== '' ? $purpose : null,
			substr($body, 0, 4000),
		)];
	}

	protected function parseGermanNumber(string $s): float {
		$negative = str_starts_with($s, '-');
		$s = str_replace(['+', '-', '.'], '', $s);
		$s = str_replace(',', '.', $s);
		$v = (float)$s;
		return $negative ? -$v : $v;
	}
}
