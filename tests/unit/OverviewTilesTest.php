<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Tests;

use OCA\LeiterrundeFinanzen\Module\FinanzenModule;
use PHPUnit\Framework\TestCase;

class OverviewTilesTest extends TestCase {
	public function testTileIdsAndCashflow(): void {
		$perm = $this->createMock(\OCA\Leiterrunde\Service\PermissionService::class);
		$perm->method('has')->willReturn(true);
		$provider = $this->createMock(\OCA\LeiterrundeFinanzen\Service\ObligationProvider::class);
		$expense = $this->createMock(\OCA\LeiterrundeFinanzen\Db\ExpenseMapper::class);
		$expense->method('findFiltered')->willReturn([]);
		$ob = $this->createMock(\OCA\LeiterrundeFinanzen\Db\ObligationMapper::class);
		$ob->method('findAll')->willReturn([]);
		$tx = $this->createMock(\OCA\LeiterrundeFinanzen\Db\TransactionMapper::class);
		$tx->method('findFiltered')->willReturn([]);
		$config = $this->createMock(\OCA\LeiterrundeFinanzen\Service\ModuleConfigService::class);
		$config->method('get')->willReturn('');
		$l = $this->createMock(\OCP\IL10N::class);
		$l->method('t')->willReturnCallback(fn($s, $p = []) => $p ? vsprintf($s, $p) : $s);
		$l->method('n')->willReturnCallback(fn($s1, $s2, $n, $p = []) => vsprintf(str_replace('%n', (string)$n, $n === 1 ? $s1 : $s2), $p));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('u1');
		$m = new FinanzenModule($provider, $perm, $expense, $ob, $tx, $config, $l);
		$ids = array_map(static fn($t) => $t->id, $m->getOverviewTiles($user));
		foreach (['finance.expenses.open', 'finance.obligations.overdue', 'finance.cashflow', 'finance.obligations.status', 'finance.imap'] as $id) {
			$this->assertContains($id, $ids);
		}
	}
}
