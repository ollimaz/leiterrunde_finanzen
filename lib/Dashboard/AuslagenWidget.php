<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Dashboard;

use OCA\LeiterrundeFinanzen\AppInfo\Application;
use OCA\LeiterrundeFinanzen\Module\FinanzenModule;
use OCA\Leiterrunde\Service\PermissionService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;

class AuslagenWidget implements IAPIWidgetV2, IConditionalWidget, IIconWidget, IWidget {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private PermissionService $perm,
		private \OCA\LeiterrundeFinanzen\Module\FinanzenModule $module,
	) {
	}

	public function getId(): string { return 'leiterrunde-auslagen'; }
	public function getTitle(): string { return 'Leiterrunde: Open expenses'; }
	public function getOrder(): int { return 20; }
	public function getIconClass(): string { return 'icon-leiterrunde'; }
	public function getIconUrl(): string { return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')); }
	public function getUrl(): ?string { return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index'); }
	public function load(): void {}
	public function isEnabled(): bool {
		$uid = $this->userSession->getUser()?->getUID();
		return $uid !== null && ($this->perm->has('expenses.decide', $uid));
	}
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new WidgetItems([], '', '');
		}
		$items = [];
		try {
			foreach ($this->module->getOverviewTiles($user) as $tile) {
				if ($tile->title !== 'Open expenses') {
					continue;
				}
				foreach (array_slice($tile->items, 0, $limit) as $i) {
					$items[] = new WidgetItem((string)($i['label'] ?? ''), (string)($i['sublabel'] ?? ''));
				}
			}
		} catch (\Throwable) {
		}
		return new WidgetItems($items, '', '');
	}
}
