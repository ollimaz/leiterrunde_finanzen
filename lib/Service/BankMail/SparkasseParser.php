<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\BankMail;

/**
 * Variante für Sparkassen-"Kontoumsatz"-Push-Mails.
 */
class SparkasseParser extends GenericParser {
	public function key(): string {
		return 'sparkasse';
	}

	public function label(): string {
		return 'Sparkasse (Kontoumsatz-Mail)';
	}

	public function parse(MailMessage $message): array {
		$body = $message->body;
		if (preg_match('/(?:Kontoumsatz|Umsatz|Betrag)\s*(?:auf|von|für)?\s*:?\s*([+-]?[\d.]+,\d{2})\s*(?:EUR|Euro|€)/iu', $body, $mAmount) !== 1) {
			return [];
		}
		$amount = $this->parseGermanNumber($mAmount[1]);
		if (preg_match('/Gutschrift|Eingang/iu', $body) === 1) {
			if ($amount < 0) {
				$amount = -$amount;
			}
		} elseif (preg_match('/Lastschrift|Belastung|Abbuchung|Ausgang/iu', $body) === 1) {
			if ($amount >= 0) {
				$amount = -$amount;
			}
		}
		$date = null;
		if (preg_match('/(?:am|vom|Datum|Buchungstag|Valuta)\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{4})/iu', $body, $mDate) === 1
			|| preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{4})\b/', $body, $mDate) === 1) {
			$d = \DateTimeImmutable::createFromFormat('!d.m.Y', $mDate[1]);
			if ($d !== false) {
				$date = $d->format('Y-m-d');
			}
		}
		$counterparty = null;
		if (preg_match('/(?:von|an|Auftraggeber|Empfänger)\s*:?\s*([^\r\n]{2,})/iu', $body, $mCp) === 1) {
			$counterparty = trim($mCp[1]);
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
}
