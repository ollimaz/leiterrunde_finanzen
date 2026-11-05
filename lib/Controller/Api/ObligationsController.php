<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Db\Obligation;
use OCA\LeiterrundeFinanzen\Db\ObligationMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeFinanzen\Service\TransactionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ObligationsController extends ApiController {
	private const STATUSES = ['open', 'partial', 'paid', 'cancelled'];

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ObligationMapper $mapper,
		private TransactionService $txService,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?string $status = null): JSONResponse {
		return $this->wrap(function () use ($status) {
			$this->perm->require('finance.read');
			$out = [];
			foreach ($this->mapper->findAll($status) as $o) {
				$row = $o->jsonSerialize();
				$row['remaining'] = $this->txService->remaining($o);
				$out[] = $row;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('finance.read');
			$o = $this->mapper->find($id);
			$row = $o->jsonSerialize();
			$row['remaining'] = $this->txService->remaining($o);
			return $row;
		});
	}

	#[NoAdminRequired]
	public function create(
		string $label,
		float $amount_due,
		?string $payer_name = null,
		?string $due_date = null,
		?string $reference = null,
		?int $person_id = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($label, $amount_due, $payer_name, $due_date, $reference, $person_id, $notes) {
			$this->perm->require('finance.write');
			if ($reference !== null && $reference !== '' && $this->mapper->findByReference($reference) !== null) {
				throw new ApiException('Reference already taken', 409);
			}
			$o = new Obligation();
			$o->setLabel(trim($label));
			$o->setAmountDue(number_format($amount_due, 2, '.', ''));
			$o->setPayerName($payer_name);
			$o->setDueDate($due_date);
			$o->setReference($reference);
			$o->setPersonId($person_id);
			$o->setNotes($notes);
			$o->setStatus('open');
			$o->setCreatedAt(time());
			$o->setUpdatedAt(time());
			$o = $this->mapper->insert($o);
			$this->audit->diff('obligation', $o->getId(), null, ['_action' => 'created']);
			return new JSONResponse($o->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $label = null,
		?float $amount_due = null,
		?string $payer_name = null,
		?string $due_date = null,
		?string $reference = null,
		?string $status = null,
		?int $person_id = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $label, $amount_due, $payer_name, $due_date, $reference, $status, $person_id, $notes) {
			$this->perm->require('finance.write');
			$o = $this->mapper->find($id);
			$before = $o->jsonSerialize();
			if ($label !== null) $o->setLabel($label);
			if ($amount_due !== null) $o->setAmountDue(number_format($amount_due, 2, '.', ''));
			if ($payer_name !== null) $o->setPayerName($payer_name);
			if ($due_date !== null) $o->setDueDate($due_date);
			if ($reference !== null) $o->setReference($reference);
			if ($person_id !== null) $o->setPersonId($person_id);
			if ($notes !== null) $o->setNotes($notes);
			if ($status !== null) {
				if (!in_array($status, self::STATUSES, true)) {
					throw new ApiException('Invalid status', 422);
				}
				$o->setStatus($status);
			}
			$o->setUpdatedAt(time());
			$o = $this->mapper->update($o);
			if ($status === null) {
				$this->txService->recomputeStatus($o->getId());
			}
			$this->audit->diff('obligation', $o->getId(), $before, $o->jsonSerialize());
			return $o->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('finance.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('obligation', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}
}
