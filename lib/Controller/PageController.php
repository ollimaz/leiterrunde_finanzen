<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Controller;

use OCA\LeiterrundeFinanzen\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	public function __construct(IRequest $request, private IAppManager $appManager) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if (!$this->appManager->isEnabledForAnyone(Application::CORE_APP_ID)) {
			return new TemplateResponse(Application::APP_ID, 'core-required', [], 'blank');
		}
		Util::addScript('leiterrunde', 'lr-common');
		Util::addStyle('leiterrunde', 'leiterrunde');
		Util::addScript(Application::APP_ID, 'main', 'leiterrunde');
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
