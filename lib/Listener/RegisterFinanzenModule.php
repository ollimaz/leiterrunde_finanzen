<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Listener;

use OCA\Leiterrunde\Module\IObligationProvider;
use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCA\LeiterrundeFinanzen\Module\FinanzenModule;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegisterFinanzenModule implements IEventListener {
	public function __construct(private FinanzenModule $module, private \OCA\LeiterrundeFinanzen\Service\BookService $books) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterModulesEvent) {
			return;
		}
		$event->registerModule($this->module);
		$event->registerProvider(IObligationProvider::class, $this->module);
		$event->registerProvider(\OCA\Leiterrunde\Module\IEventAccountingProvider::class, $this->books);
	}
}
