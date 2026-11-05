<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Module;

use OCA\Leiterrunde\Module\CapabilityDefinition;
use OCA\Leiterrunde\Module\DocumentType;
use OCA\Leiterrunde\Module\IModule;
use OCA\Leiterrunde\Module\IObligationProvider;
use OCA\Leiterrunde\Module\OverviewTile;
use OCA\LeiterrundeFinanzen\Service\ObligationProvider;
use OCP\IUser;
use OCP\IL10N;

class FinanzenModule implements IModule, IObligationProvider {
	public function __construct(private ObligationProvider $provider,
		private \OCA\Leiterrunde\Service\PermissionService $perm,
		private \OCA\LeiterrundeFinanzen\Db\ExpenseMapper $expenseMapper,
		private \OCA\LeiterrundeFinanzen\Db\ObligationMapper $obligationMapper,
		private \OCA\LeiterrundeFinanzen\Db\TransactionMapper $txMapper,
		private \OCA\LeiterrundeFinanzen\Service\ModuleConfigService $config,
		private IL10N $l10n) {
	}

	public function getId(): string {
		return 'leiterrunde_finanzen';
	}

	public function getLabel(): string {
		return 'Finanzen';
	}

	public function getNavUrl(): string {
		return '/apps/leiterrunde_finanzen/';
	}

	public function getCapabilities(): array {
		return [
			new CapabilityDefinition('finance.read', $this->l10n->t('Read finances'), $this->l10n->t('Finances'), ['kasse', 'vorstand', 'admin']),
			new CapabilityDefinition('finance.write', $this->l10n->t('Write finances'), $this->l10n->t('Finances'), ['kasse', 'admin']),
			new CapabilityDefinition('expenses.submitOwn', $this->l10n->t('Submit own expenses'), $this->l10n->t('Finances'), ['any']),
			new CapabilityDefinition('expenses.readAll', $this->l10n->t('Read all expenses'), $this->l10n->t('Finances'), ['kasse', 'vorstand', 'admin']),
			new CapabilityDefinition('expenses.decide', $this->l10n->t('Decide expenses'), $this->l10n->t('Finances'), ['kasse', 'vorstand', 'admin']),
			new CapabilityDefinition('expenses.pay', $this->l10n->t('Pay out expenses'), $this->l10n->t('Finances'), ['kasse', 'admin']),
		];
	}

	public function getDocumentTypes(): array {
		return [
			new DocumentType('beleg', 'Belege (Auslagen)',
				'Leiterrunde/Belege/{jahr}/{kategorie}',
				['jahr', 'monat', 'datum', 'vorname', 'nachname', 'kategorie', 'id']),
		];
	}

	public function getOverviewTiles(IUser $user): array {
		$tiles = [];
		$uid = $user->getUID();
		if ($this->perm->has('expenses.decide', $uid)) {
			$open = $this->expenseMapper->findFiltered(null, 'pending');
			$items = array_slice(array_map(static fn ($e) => ['label' => $e->getCategory() . ' ' . number_format((float)$e->getAmount(), 2, ',', '.') . ' €'], $open), 0, 5);
			$tiles[] = new OverviewTile('finance.expenses.open', $this->l10n->t('Open expenses'), count($open) > 0 ? 'warn' : 'ok', count($open), $items, '/apps/leiterrunde_finanzen/#/auslagen');
		}
		if ($this->perm->has('finance.read', $uid)) {
			$overdue = [];
			foreach ($this->obligationMapper->findAll() as $o) {
				if ($o->getStatus() === 'open' && $o->getDueDate() !== null && $o->getDueDate() < date('Y-m-d')) {
					$overdue[] = $o;
				}
			}
			$items = array_slice(array_map(static fn ($o) => ['label' => $o->getLabel(), 'sublabel' => $o->getDueDate()], $overdue), 0, 5);
			$tiles[] = new OverviewTile('finance.obligations.overdue', $this->l10n->t('Overdue payments'), count($overdue) > 0 ? 'fail' : 'ok', count($overdue), $items, '/apps/leiterrunde_finanzen/#/zahlungen');
			$months = [];
			for ($i = 11; $i >= 0; $i--) {
				$m = (new \DateTimeImmutable('first day of this month'))->modify('-' . $i . ' months');
				$months[$m->format('Y-m')] = ['label' => $m->format('m/y'), 'value' => 0.0, 'secondary' => 0.0];
			}
			$since = (new \DateTimeImmutable('-11 months'))->format('Y-m-01');
			foreach ($this->txMapper->findFiltered(null, null, 2000) as $tx) {
				if ($tx->getBookingDate() < $since) { continue; }
				$k = substr($tx->getBookingDate(), 0, 7);
				if (isset($months[$k])) {
					$a = (float)$tx->getAmount();
					if ($a >= 0) { $months[$k]['value'] += $a; } else { $months[$k]['secondary'] += -$a; }
				}
			}
			$tiles[] = new OverviewTile('finance.cashflow', $this->l10n->t('Income & expenditure'), 'info', null, [], '/apps/leiterrunde_finanzen/#/umsaetze', null,
				['type' => 'columns', 'unit' => '€', 'series' => array_values($months)]);
			$byStatus = [];
			$openSum = 0.0;
			foreach ($this->obligationMapper->findAll() as $o) {
				if (in_array($o->getStatus(), ['open', 'partial'], true)) {
					$openSum += (float)$o->getAmountDue();
					$k = ($o->getDueDate() !== null && $o->getDueDate() < date('Y-m-d')) ? 'overdue' : $o->getStatus();
					$byStatus[$k] = ($byStatus[$k] ?? 0) + (float)$o->getAmountDue();
				}
			}
			$tiles[] = new OverviewTile('finance.obligations.status', $this->l10n->t('Open payments'), count($byStatus) > 0 ? 'warn' : 'ok', null, [], '/apps/leiterrunde_finanzen/#/zahlungen',
				['value' => number_format($openSum, 2, ',', '.') . ' €', 'label' => $this->l10n->t('open')],
				['type' => 'stack', 'unit' => '€', 'series' => array_map(static fn ($k, $v) => ['label' => $k, 'value' => $v, 'state' => $k === 'overdue' ? 'fail' : ($k === 'partial' ? 'warn' : 'info')], array_keys($byStatus), $byStatus)]);
		}
		if ($this->perm->has('finance.write', $uid)) {
			$st = \OCA\LeiterrundeFinanzen\Service\ImapStatus::parse($this->config->get('imap_last_status'));
			$label = $st === null ? $this->l10n->t('Not fetched yet')
				: ($st['raw'] ?? ($this->l10n->n('%n mail', '%n mails', $st['mails']) . ', ' . $this->l10n->t('%s imported', [$st['imported']])
					. ($st['errors'] > 0 ? ', ' . $this->l10n->n('%n error', '%n errors', $st['errors']) : '')
					. ($st['message'] !== null ? ': ' . $st['message'] : '')));
			$tiles[] = new OverviewTile('finance.imap', $this->l10n->t('Last IMAP fetch'), ($st !== null && !$st['ok']) ? 'fail' : 'ok', null,
				[['label' => $label, 'ts' => $st !== null ? $st['ts'] : null]], '/apps/leiterrunde_finanzen/#/import');
		}
		return $tiles;
	}

	public function ensure(string $reference, string $payerName, string $label, string $amountDue, ?string $dueDate, ?int $registrationId, ?int $personId): int {
		return $this->provider->ensure($reference, $payerName, $label, $amountDue, $dueDate, $registrationId, $personId);
	}

	public function cancelIfUnpaid(int $id): void {
		$this->provider->cancelIfUnpaid($id);
	}

	public function status(int $id): ?array {
		return $this->provider->status($id);
	}
}
