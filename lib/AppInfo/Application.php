<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\AppInfo;

use OCA\LeiterrundeFinanzen\Listener\RegisterFinanzenModule;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Application extends App implements IBootstrap {
	public const APP_ID = 'leiterrunde_finanzen';
	public const CORE_APP_ID = 'leiterrunde';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(
			'OCA\Leiterrunde\Module\RegisterModulesEvent',
			RegisterFinanzenModule::class
		);
		// Widgets brauchen Core-Klassen — bei deaktivierter Core-App würden
		// sie fremde Seiten (z.B. das Dashboard) zum Fehler machen.
		if (Server::get(IAppManager::class)->isEnabledForAnyone(self::CORE_APP_ID)) {
			$context->registerDashboardWidget(\OCA\LeiterrundeFinanzen\Dashboard\AuslagenWidget::class);
		}
	}

	public function boot(IBootContext $context): void {
	}
}
