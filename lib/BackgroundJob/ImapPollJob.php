<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\BackgroundJob;

use OCA\LeiterrundeFinanzen\Service\ImapImportService;
use OCA\LeiterrundeFinanzen\Service\ImapStatus;
use OCA\LeiterrundeFinanzen\Service\ModuleConfigService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ImapPollJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ImapImportService $importService,
		private ModuleConfigService $settings,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		try {
			if (!$this->settings->getBool('imap_enabled')) {
				return;
			}
			$interval = $this->settings->getInt('imap_interval', 15);
			$last = $this->settings->getInt('imap_last_poll', 0);
			if ($last > 0 && time() < $last + $interval * 60) {
				return;
			}
			$this->importService->poll();
		} catch (\Throwable $e) {
			try {
				$this->settings->set('imap_last_status', ImapStatus::encode(false, time(), [], $e->getMessage()));
			} catch (\Throwable) {
			}
			$this->logger->warning('Leiterrunde ImapPollJob: ' . $e->getMessage());
		}
	}
}
