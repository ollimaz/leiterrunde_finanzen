<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Api\\Accounts#index', 'url' => '/api/accounts', 'verb' => 'GET'],
		['name' => 'Api\\Accounts#parsers', 'url' => '/api/accounts/parsers', 'verb' => 'GET'],
		['name' => 'Api\\Accounts#create', 'url' => '/api/accounts', 'verb' => 'POST'],
		['name' => 'Api\\Accounts#update', 'url' => '/api/accounts/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Accounts#destroy', 'url' => '/api/accounts/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Transactions#index', 'url' => '/api/transactions', 'verb' => 'GET'],
		['name' => 'Api\\Transactions#import', 'url' => '/api/transactions/import', 'verb' => 'POST'],
		['name' => 'Api\\Transactions#show', 'url' => '/api/transactions/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Transactions#assign', 'url' => '/api/transactions/{id}/assign', 'verb' => 'POST'],
		['name' => 'Api\\Obligations#index', 'url' => '/api/obligations', 'verb' => 'GET'],
		['name' => 'Api\\Obligations#create', 'url' => '/api/obligations', 'verb' => 'POST'],
		['name' => 'Api\\Obligations#show', 'url' => '/api/obligations/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Obligations#update', 'url' => '/api/obligations/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Obligations#destroy', 'url' => '/api/obligations/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Expenses#categories', 'url' => '/api/expenses/categories', 'verb' => 'GET'],
		['name' => 'Api\\Expenses#index', 'url' => '/api/expenses', 'verb' => 'GET'],
		['name' => 'Api\\Expenses#create', 'url' => '/api/expenses', 'verb' => 'POST'],
		['name' => 'Api\\Expenses#show', 'url' => '/api/expenses/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Expenses#destroy', 'url' => '/api/expenses/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Expenses#approve', 'url' => '/api/expenses/{id}/approve', 'verb' => 'POST'],
		['name' => 'Api\\Expenses#reject', 'url' => '/api/expenses/{id}/reject', 'verb' => 'POST'],
		['name' => 'Api\\Expenses#pay', 'url' => '/api/expenses/{id}/pay', 'verb' => 'POST'],
		['name' => 'Api\\Expenses#file', 'url' => '/api/expenses/{id}/file', 'verb' => 'GET'],
		['name' => 'Api\\Imap#poll', 'url' => '/api/imap/poll', 'verb' => 'POST'],
		['name' => 'Api\\Imap#test', 'url' => '/api/imap/test', 'verb' => 'POST'],
		['name' => 'Api\\Settings#show', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'Api\\Settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
	],
];
