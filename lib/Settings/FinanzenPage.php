<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class FinanzenPage implements IDelegatedSettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse('leiterrunde_finanzen', 'settings-finanzen', []);
	}

	public function getSection(): string {
		return 'leiterrunde';
	}

	public function getPriority(): int {
		return 60;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
