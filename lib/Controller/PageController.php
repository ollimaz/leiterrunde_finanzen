<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller;

use OCA\Leiterrunde\Controller\ModulePageController;
use OCA\LeiterrundeFinanzen\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IRequest;

class PageController extends ModulePageController {
	public function __construct(IRequest $request, IAppManager $appManager) {
		parent::__construct(Application::APP_ID, $request, $appManager);
	}
}
