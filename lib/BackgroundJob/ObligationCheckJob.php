<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\BackgroundJob;

use OCA\LeiterrundeFinanzen\Db\ObligationMapper;
use OCA\Leiterrunde\Service\NotificationService;
use OCA\Leiterrunde\Service\SettingsService;
use OCA\Leiterrunde\Service\StateService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ObligationCheckJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ObligationMapper $obligationMapper,
		private StateService $stateService,
		private NotificationService $notificationService,
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$today = new \DateTimeImmutable('today');
		foreach ($this->obligationMapper->findAll() as $o) {
			try {
				if (!in_array($o->getStatus(), ['open', 'partial'], true)) {
					continue;
				}
				$due = $o->getDueDate();
				$overdue = $due !== null && $due < $today->format('Y-m-d');
				$new = $overdue ? 'overdue' : 'ok';
				$old = $this->stateService->transition('obligation', $o->getId(), 'overdue', $new);
				if ($new === 'overdue' && $old !== 'overdue') {
					$this->notificationService->notifyState(
						'obligation_overdue',
						['person_id' => 0, 'check_label' => $o->getLabel(), 'state' => 'overdue', 'first_name' => (string)$o->getPayerName(), 'last_name' => ''],
						$this->settings->notifyGroups('finance'),
						null,
						'Overdue payment obligation',
						"Hello,\n\nthe payment obligation „{$o->getLabel()}" . '" (' . (string)$o->getPayerName() . ", {$o->getAmountDue()} EUR, due {$due}) is overdue.\n",
					);
				}
			} catch (\Throwable $e) {
				$this->logger->warning('Leiterrunde ObligationCheckJob: ' . $e->getMessage());
			}
		}
	}
}
