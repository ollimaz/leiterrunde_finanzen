<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Db\Account;
use OCA\LeiterrundeFinanzen\Db\AccountMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\LeiterrundeFinanzen\Service\BankMail\ParserRegistry;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AccountsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private AccountMapper $mapper,
		private ParserRegistry $parsers,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('finance.read');
			return array_map(static fn ($a) => $a->jsonSerialize(), $this->mapper->findAll());
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function parsers(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('finance.read');
			return $this->parsers->all();
		});
	}

	#[NoAdminRequired]
	public function create(
		string $name,
		?string $iban = null,
		?string $parser_key = null,
		?string $mail_hint = null,
		bool $active = true,
	): JSONResponse {
		return $this->wrap(function () use ($name, $iban, $parser_key, $mail_hint, $active) {
			$this->perm->require('finance.write');
			if (trim($name) === '') {
				throw new ApiException('Name required', 422);
			}
			$a = new Account();
			$a->setName(trim($name));
			$a->setIban($iban);
			$a->setParserKey($parser_key);
			$a->setMailHint($mail_hint);
			$a->setActive($active);
			$a = $this->mapper->insert($a);
			$this->audit->diff('account', $a->getId(), null, ['_action' => 'created']);
			return new JSONResponse($a->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $name = null,
		?string $iban = null,
		?string $parser_key = null,
		?string $mail_hint = null,
		?bool $active = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $name, $iban, $parser_key, $mail_hint, $active) {
			$this->perm->require('finance.write');
			$a = $this->mapper->find($id);
			$before = $a->jsonSerialize();
			if ($name !== null) $a->setName(trim($name));
			if ($iban !== null) $a->setIban($iban);
			if ($parser_key !== null) $a->setParserKey($parser_key);
			if ($mail_hint !== null) $a->setMailHint($mail_hint);
			if ($active !== null) $a->setActive($active);
			$a = $this->mapper->update($a);
			$this->audit->diff('account', $a->getId(), $before, $a->jsonSerialize());
			return $a->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('finance.write');
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('account', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}
}
