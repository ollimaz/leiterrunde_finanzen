<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Db\AccountMapper;
use OCA\LeiterrundeFinanzen\Db\TransactionMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\LeiterrundeFinanzen\Service\Import\Camt053Importer;
use OCA\LeiterrundeFinanzen\Service\Import\CsvImporter;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeFinanzen\Service\TransactionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TransactionsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private TransactionMapper $mapper,
		private AccountMapper $accountMapper,
		private TransactionService $service,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $account_id = null, ?string $unassigned = null): JSONResponse {
		return $this->wrap(function () use ($account_id, $unassigned) {
			$this->perm->require('finance.read');
			$onlyUnassigned = in_array($unassigned, ['1', 'true'], true);
			return array_map(static fn ($t) => $t->jsonSerialize(), $this->mapper->findFiltered($account_id, $onlyUnassigned ?: null));
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('finance.read');
			return $this->mapper->find($id)->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function import(int $account_id, string $format): JSONResponse {
		return $this->wrap(function () use ($account_id, $format) {
			$this->perm->require('finance.write');
			$this->accountMapper->find($account_id);
			$file = $this->request->getUploadedFile('file');
			if (!is_array($file) || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
				throw new ApiException('No file uploaded', 400);
			}
			$content = file_get_contents($file['tmp_name']);
			if ($content === false || $content === '') {
				throw new ApiException('Empty file', 400);
			}
			$parsed = match ($format) {
				'csv' => (new CsvImporter())->parse($content),
				'camt053', 'camt' => (new Camt053Importer())->parse($content),
				default => throw new ApiException('Unbekanntes Format (csv|camt053)', 400),
			};
			if ($parsed === []) {
				throw new ApiException('No transactions found in the file', 422);
			}
			$imported = $duplicates = $matched = 0;
			$errors = [];
			foreach ($parsed as $i => $pt) {
				try {
					$res = $this->service->add($account_id, $pt, $format === 'csv' ? 'csv' : 'camt');
					if ($res['result'] === TransactionService::RESULT_DUPLICATE) {
						$duplicates++;
					} else {
						$imported++;
						if ($res['result'] === TransactionService::RESULT_MATCHED) {
							$matched++;
						}
					}
				} catch (\Throwable $e) {
					$errors[] = ['line' => $i + 1, 'message' => $e->getMessage()];
				}
			}
			return ['imported' => $imported, 'duplicates' => $duplicates, 'matched' => $matched, 'errors' => $errors];
		});
	}

	#[NoAdminRequired]
	public function assign(int $id, ?int $obligation_id = null): JSONResponse {
		return $this->wrap(function () use ($id, $obligation_id) {
			$this->perm->require('finance.write');
			$tx = $this->mapper->find($id);
			$this->service->assign($tx, $obligation_id);
			$this->audit->diff('transaction', $id, null, ['_action' => 'assigned:' . ($obligation_id ?? 'none')]);
			return $tx->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('finance.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('transaction', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}
}
