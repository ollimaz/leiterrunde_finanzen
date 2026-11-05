<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\LeiterrundeFinanzen\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Security\ICrypto;

class ModuleConfigService {
	private const ENCRYPTED = ['imap_password'];
	private const KEYS = [
		'imap_host', 'imap_port', 'imap_tls', 'imap_user', 'imap_password',
		'imap_folder', 'imap_interval', 'imap_enabled', 'imap_last_poll', 'imap_last_status',
		'expense_categories',
	];

	public function __construct(
		private IAppConfig $appConfig,
		private ICrypto $crypto,
	) {
	}

	public function get(string $key, string $default = ''): string {
		$raw = $this->appConfig->getValueString(Application::APP_ID, $key, $default === '' ? '' : $default, in_array($key, self::ENCRYPTED, true));
		if ($raw !== '' && in_array($key, self::ENCRYPTED, true)) {
			try {
				return $this->crypto->decrypt($raw);
			} catch (\Throwable) {
				return $raw;
			}
		}
		return $raw === '' ? $default : $raw;
	}

	public function getInt(string $key, int $default = 0): int {
		return (int)$this->get($key, (string)$default);
	}

	public function getBool(string $key): bool {
		return $this->get($key) === '1' || $this->get($key) === 'true';
	}

	public function set(string $key, string $value): void {
		if (in_array($key, self::ENCRYPTED, true)) {
			$this->appConfig->setValueString(Application::APP_ID, $key, $this->crypto->encrypt($value), true);
			return;
		}
		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}

	public function all(): array {
		$out = [];
		foreach (self::KEYS as $k) {
			$out[$k] = $k === 'imap_password' ? ($this->get($k) !== '' ? 'gesetzt' : '') : $this->get($k);
		}
		return $out;
	}
}
