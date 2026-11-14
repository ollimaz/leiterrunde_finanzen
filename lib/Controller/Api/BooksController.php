<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\LeiterrundeFinanzen\Service\BookService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class BooksController extends ApiController {
	public function __construct(IRequest $request, LoggerInterface $logger, private BookService $books) {
		parent::__construct('leiterrunde_finanzen', $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function options(): JSONResponse {
		return $this->wrap(fn () => $this->books->options());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(fn () => $this->books->summary());
	}

	#[NoAdminRequired]
	public function create(string $name, string $kind, ?int $event_id = null, ?int $account_id = null): JSONResponse {
		return $this->wrap(fn () => new JSONResponse(
			$this->books->create($name, $event_id === 0 ? null : $event_id, $kind, $account_id === 0 ? null : $account_id), Http::STATUS_CREATED
		));
	}
}
