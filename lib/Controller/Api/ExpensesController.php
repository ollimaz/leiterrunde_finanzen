<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Db\Expense;
use OCA\LeiterrundeFinanzen\Db\ExpenseMapper;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\DocumentStorageService;
use OCA\Leiterrunde\Service\NotificationService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\Leiterrunde\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ExpensesController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ExpenseMapper $mapper,
		private PersonMapper $personMapper,
		private DocumentStorageService $files,
		private AuditService $audit,
		private NotificationService $notifications,
		private SettingsService $settings,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function categories(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('expenses.submitOwn');
			$raw = (string)$this->settings->get('expense_categories', '');
			return array_values(array_filter(array_map('trim', explode(',', $raw))));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $person_id = null, ?string $status = null): JSONResponse {
		return $this->wrap(function () use ($person_id, $status) {
			if ($this->perm->has('expenses.readAll')) {
				return array_map(static fn ($e) => $e->jsonSerialize(), $this->mapper->findFiltered($person_id, $status));
			}
			$this->perm->require('expenses.submitOwn');
			$own = $this->perm->currentPerson();
			if ($own === null) {
				return [];
			}
			return array_map(static fn ($e) => $e->jsonSerialize(), $this->mapper->findFiltered($own->getId(), $status));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$e = $this->mapper->find($id);
			$this->assertCanView($e);
			return $e->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function create(
		int $person_id,
		string $expense_date,
		float $amount,
		?string $category = null,
		?string $description = null,
	): JSONResponse {
		return $this->wrap(function () use ($person_id, $expense_date, $amount, $category, $description) {
			$own = $this->perm->currentPerson();
			$isOwn = $own !== null && $own->getId() === $person_id;
			if ($isOwn) {
				$this->perm->require('expenses.submitOwn');
			} else {
				$this->perm->require('finance.write');
			}
			$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $expense_date);
			if ($d === false || $amount <= 0) {
				throw new ApiException('Invalid data', 422);
			}
			$user = $this->perm->requireLoggedIn();
			$e = new Expense();
			$e->setPersonId($person_id);
			$e->setSubmittedBy($user->getUID());
			$e->setExpenseDate($d->format('Y-m-d'));
			$e->setAmount(number_format($amount, 2, '.', ''));
			$e->setCategory($category);
			$e->setDescription($description);
			$e->setStatus('pending');
			$e->setCreatedAt(time());
			$upload = $this->request->getUploadedFile('file');
			if (is_array($upload) && !empty($upload['tmp_name']) && is_file($upload['tmp_name'])) {
				$vars = $this->docVars($person_id);

				$stored = $this->files->store('beleg', $user->getUID(), $upload, $vars);
				$e->setFileId($stored['file_id']);
				$e->setFileOwner($stored['file_owner']);
				$e->setFileName($stored['file_name']);
			}
			$e = $this->mapper->insert($e);
			$this->audit->diff('expense', $e->getId(), null, ['_action' => 'created']);
			$person = $own ?? null;
			try {
				$person ??= $this->personMapper->find($person_id);
			} catch (\Throwable) {
			}
			$this->notifications->notifyState(
				'expense_submitted',
				['person_id' => $person_id, 'first_name' => $person?->getFirstName() ?? '', 'last_name' => $person?->getLastName() ?? ''],
				$this->settings->notifyGroups('finance'),
				null,
				'New expense',
				"Hello,\n\n" . trim(($person?->getFirstName() ?? '') . ' ' . ($person?->getLastName() ?? '')) . " submitted an expense of {$e->getAmount()} EUR.\n",
			);
			return new JSONResponse($e->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function approve(int $id, ?string $decision_note = null): JSONResponse {
		return $this->decide($id, 'approved', $decision_note);
	}

	#[NoAdminRequired]
	public function reject(int $id, ?string $decision_note = null): JSONResponse {
		return $this->decide($id, 'rejected', $decision_note);
	}

	private function decide(int $id, string $status, ?string $note): JSONResponse {
		return $this->wrap(function () use ($id, $status, $note) {
			$this->perm->require('expenses.decide');
			$e = $this->mapper->find($id);
			if ($e->getStatus() !== 'pending') {
				throw new ApiException('Only open expenses can be decided', 409);
			}
			$before = $e->jsonSerialize();
			$e->setStatus($status);
			$e->setDecidedBy($this->perm->requireLoggedIn()->getUID());
			$e->setDecidedAt(time());
			$e->setDecisionNote($note);
			$e = $this->mapper->update($e);
			$this->audit->diff('expense', $e->getId(), $before, $e->jsonSerialize());
			return $e->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function pay(int $id, ?int $transaction_id = null): JSONResponse {
		return $this->wrap(function () use ($id, $transaction_id) {
			$this->perm->require('expenses.pay');
			$e = $this->mapper->find($id);
			if ($e->getStatus() !== 'approved') {
				throw new ApiException('Only approved expenses can be marked as paid', 409);
			}
			$before = $e->jsonSerialize();
			$e->setStatus('paid');
			$e->setPaidAt(time());
			$e->setTransactionId($transaction_id);
			$e = $this->mapper->update($e);
			$this->audit->diff('expense', $e->getId(), $before, $e->jsonSerialize());
			return $e->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$e = $this->mapper->find($id);
			$own = $this->perm->currentPerson();
			$isOwn = $own !== null && $own->getId() === $e->getPersonId()
				&& $e->getSubmittedBy() === $this->perm->requireLoggedIn()->getUID();
			if (!$isOwn || $e->getStatus() !== 'pending') {
				$this->perm->require('expenses.decide');
			}
			$this->mapper->delete($e);
			$this->audit->diff('expense', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function file(int $id): Response {
		try {
			$e = $this->mapper->find($id);
			$this->assertCanView($e);
			if ($e->getFileId() === null || $e->getFileOwner() === null) {
				throw new ApiException('No file present', 404);
			}
			$file = $this->files->get($e->getFileOwner(), $e->getFileId());
			$res = new StreamResponse($file->fopen('rb'));
			$res->addHeader('Content-Type', $file->getMimeType());
			$res->addHeader('Content-Disposition', 'inline; filename="' . addslashes($e->getFileName() ?? 'beleg') . '"');
			return $res;
		} catch (ApiException $ex) {
			return new JSONResponse(['error' => $ex->getMessage()], $ex->getStatus());
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return new JSONResponse(['error' => 'Nicht gefunden'], 404);
		}
	}

	private function docVars(int $personId): array {
		$person = null;
		try { $person = $this->personMapper->find($personId); } catch (\Throwable) {}
		$now = new \DateTimeImmutable();
		return [
			'jahr' => $now->format('Y'), 'monat' => $now->format('m'), 'datum' => $now->format('Y-m-d'),
			'vorname' => $person?->getFirstName() ?? '', 'nachname' => $person?->getLastName() ?? '',
			'id' => (string)$personId, 'kategorie' => (string)($this->request->getParam('category') ?? ''),
		];
	}

	private function assertCanView(Expense $e): void {
		if ($this->perm->has('expenses.readAll')) {
			return;
		}
		$own = $this->perm->currentPerson();
		if ($own !== null && $own->getId() === $e->getPersonId()) {
			return;
		}
		throw new \OCA\Leiterrunde\Exception\ForbiddenException();
	}
}
