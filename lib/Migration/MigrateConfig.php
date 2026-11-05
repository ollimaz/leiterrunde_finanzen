<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Migration;

use OCA\LeiterrundeFinanzen\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/** Kopiert IMAP/Finanzen-Settings aus dem Core-Config-Space (idempotent). */
class MigrateConfig implements IRepairStep {
	private const KEYS = [
		'imap_host', 'imap_port', 'imap_tls', 'imap_user', 'imap_password',
		'imap_folder', 'imap_interval', 'imap_enabled', 'imap_last_poll', 'imap_last_status',
		'expense_categories',
	];

	public function __construct(private IAppConfig $appConfig) {
	}

	public function getName(): string {
		return 'Leiterrunde Finances: migrate settings';
	}

	public function run(IOutput $output): void {
		foreach (self::KEYS as $key) {
			if ($this->appConfig->getValueString(Application::APP_ID, $key, '') !== '') {
				continue;
			}
			$old = $this->appConfig->getValueString('leiterrunde', $key, '', $key === 'imap_password');
			if ($old !== '') {
				$this->appConfig->setValueString(Application::APP_ID, $key, $old, $key === 'imap_password');
			}
		}
	}
}
