<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller\Api;

use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Service\ImapImportService;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ImapController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ImapImportService $importService,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	public function poll(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('finance.write');
			return $this->importService->poll();
		});
	}

	#[NoAdminRequired]
	public function test(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('finance.write');
			return $this->importService->test();
		});
	}
}
