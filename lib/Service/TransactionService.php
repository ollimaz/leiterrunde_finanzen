<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\LeiterrundeFinanzen\Db\Obligation;
use OCA\LeiterrundeFinanzen\Db\ObligationMapper;
use OCA\LeiterrundeFinanzen\Db\Transaction;
use OCA\LeiterrundeFinanzen\Db\TransactionMapper;
use OCA\LeiterrundeFinanzen\Service\BankMail\ParsedTransaction;

class TransactionService {
	public const RESULT_MATCHED = 'matched';
	public const RESULT_UNMATCHED = 'unmatched';
	public const RESULT_DUPLICATE = 'duplicate';

	public function __construct(
		private TransactionMapper $txMapper,
		private ObligationMapper $obligationMapper,
		private ObligationMatcher $matcher,
	) {
	}

	public static function dedupeHash(int $accountId, string $bookingDate, float $amount, ?string $counterparty, ?string $purpose): string {
		return hash('sha256', implode('|', [
			$accountId,
			$bookingDate,
			number_format($amount, 2, '.', ''),
			ObligationMatcher::normalize((string)$counterparty),
			ObligationMatcher::normalize((string)$purpose),
		]));
	}

	/**
	 * Insert a transaction; dedupe + auto-match (nur bei Betrag > 0).
	 * @return array{result: string, transaction: ?Transaction}
	 */
	public function add(int $accountId, ParsedTransaction $pt, string $source, ?string $raw = null): array {
		$hash = self::dedupeHash($accountId, $pt->bookingDate, $pt->amount, $pt->counterparty, $pt->purpose);
		if ($this->txMapper->findByHash($hash) !== null) {
			return ['result' => self::RESULT_DUPLICATE, 'transaction' => null];
		}
		$tx = new Transaction();
		$tx->setAccountId($accountId);
		$tx->setBookingDate($pt->bookingDate);
		$tx->setAmount(number_format($pt->amount, 2, '.', ''));
		$tx->setCounterparty($pt->counterparty);
		$tx->setPurpose($pt->purpose);
		$tx->setSource($source);
		$tx->setDedupeHash($hash);
		$tx->setRaw($raw ?? $pt->raw);
		$tx->setCreatedAt(time());
		$tx = $this->txMapper->insert($tx);
		$result = self::RESULT_UNMATCHED;
		if ($pt->amount > 0) {
			$obligationId = $this->autoMatch($tx);
			if ($obligationId !== null) {
				$result = self::RESULT_MATCHED;
			}
		}
		return ['result' => $result, 'transaction' => $tx];
	}

	public function autoMatch(Transaction $tx): ?int {
		$candidates = [];
		foreach ($this->obligationMapper->findMatchable() as $o) {
			$candidates[] = [
				'id' => $o->getId(),
				'reference' => $o->getReference(),
				'amount_due' => (float)$o->getAmountDue(),
				'remaining' => $this->remaining($o),
			];
		}
		$id = $this->matcher->match((float)$tx->getAmount(), (string)$tx->getPurpose(), $candidates);
		if ($id !== null) {
			$tx->setObligationId($id);
			$this->txMapper->update($tx);
			$this->recomputeStatus($id);
		}
		return $id;
	}

	public function remaining(Obligation $o): float {
		$sum = 0.0;
		foreach ($this->txMapper->findForObligation($o->getId()) as $tx) {
			$sum += (float)$tx->getAmount();
		}
		return round((float)$o->getAmountDue() - $sum, 2);
	}

	/** Manuell zuweisen (null = Zuweisung lösen) und Status neu berechnen. */
	public function assign(Transaction $tx, ?int $obligationId): void {
		$old = $tx->getObligationId();
		$tx->setObligationId($obligationId);
		$this->txMapper->update($tx);
		if ($obligationId !== null) {
			$this->recomputeStatus($obligationId);
		}
		if ($old !== null) {
			$this->recomputeStatus($old);
		}
	}

	public function recomputeStatus(int $obligationId): void {
		try {
			$o = $this->obligationMapper->find($obligationId);
		} catch (\Throwable) {
			return;
		}
		if ($o->getStatus() === 'cancelled') {
			return;
		}
		$sum = 0.0;
		foreach ($this->txMapper->findForObligation($o->getId()) as $tx) {
			$sum += (float)$tx->getAmount();
		}
		$due = (float)$o->getAmountDue();
		$status = $sum >= $due && $due > 0 ? 'paid' : ($sum > 0 ? 'partial' : 'open');
		if ($status !== $o->getStatus()) {
			$o->setStatus($status);
			$o->setUpdatedAt(time());
			$this->obligationMapper->update($o);
		}
	}
}
