<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\LeiterrundeFinanzen\Db\Obligation;
use OCA\LeiterrundeFinanzen\Db\ObligationMapper;
use OCA\LeiterrundeFinanzen\Db\TransactionMapper;

class ObligationProvider {
	public function __construct(
		private ObligationMapper $obligationMapper,
		private TransactionMapper $txMapper,
		private TransactionService $txService,
	) {
	}

	public function ensure(string $reference, string $payerName, string $label, string $amountDue, ?string $dueDate, ?int $registrationId, ?int $personId): int {
		$o = $this->obligationMapper->findByReference($reference);
		if ($o === null) {
			$o = new Obligation();
			$o->setLabel($label);
			$o->setPayerName($payerName);
			$o->setAmountDue($amountDue);
			$o->setDueDate($dueDate);
			$o->setReference($reference);
			$o->setStatus('open');
			$o->setRegistrationId($registrationId);
			$o->setPersonId($personId);
			$o->setCreatedAt(time());
			$o->setUpdatedAt(time());
			$o = $this->obligationMapper->insert($o);
		} else {
			$changed = false;
			if ($o->getStatus() === 'open' && $this->txMapper->findForObligation($o->getId()) === []) {
				$o->setAmountDue($amountDue);
				$changed = true;
			}
			if ($o->getStatus() === 'cancelled') {
				$o->setStatus('open');
				$changed = true;
			}
			if ($o->getRegistrationId() === null && $registrationId !== null) {
				$o->setRegistrationId($registrationId);
				$changed = true;
			}
			if ($o->getPersonId() === null && $personId !== null) {
				$o->setPersonId($personId);
				$changed = true;
			}
			if ($changed) {
				$o->setUpdatedAt(time());
				$this->obligationMapper->update($o);
			}
		}
		$this->txService->recomputeStatus($o->getId());
		return $o->getId();
	}

	public function cancelIfUnpaid(int $id): void {
		try {
			$o = $this->obligationMapper->find($id);
			if ($o->getStatus() === 'open' && $this->txMapper->findForObligation($id) === []) {
				$o->setStatus('cancelled');
				$o->setUpdatedAt(time());
				$this->obligationMapper->update($o);
			}
		} catch (\Throwable) {
		}
	}

	public function status(int $id): ?array {
		try {
			$o = $this->obligationMapper->find($id);
			return ['status' => $o->getStatus(), 'remaining' => $this->txService->remaining($o)];
		} catch (\Throwable) {
			return null;
		}
	}
}
