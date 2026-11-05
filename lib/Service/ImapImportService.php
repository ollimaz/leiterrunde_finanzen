<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

use OCA\LeiterrundeFinanzen\Db\AccountMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\LeiterrundeFinanzen\Service\BankMail\ParserRegistry;
use OCA\LeiterrundeFinanzen\Service\Imap\ImapClient;
use OCA\LeiterrundeFinanzen\Service\Imap\MimeParser;
use Psr\Log\LoggerInterface;

class ImapImportService {
	public function __construct(
		private AccountMapper $accountMapper,
		private ParserRegistry $parsers,
		private TransactionService $txService,
		private ModuleConfigService $settings,
		private MimeParser $mimeParser,
		private LoggerInterface $logger,
	) {
	}

	/** Verbindung testen: connect + login + select. */
	public function test(): array {
		$client = $this->connectFromSettings();
		try {
			$client->select($this->settings->get('imap_folder', 'INBOX'));
			return ['ok' => true];
		} finally {
			$client->logout();
		}
	}

	/** Mails abrufen und Umsätze importieren. */
	public function poll(): array {
		$result = ['fetched' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'skipped' => 0, 'errors' => []];
		$client = $this->connectFromSettings();
		try {
			$client->select($this->settings->get('imap_folder', 'INBOX'));
			$uids = $client->searchUnseen();
			$result['fetched'] = count($uids);
			$accounts = $this->accountMapper->findAll(true);
			foreach ($uids as $uid) {
				try {
					$raw = $client->fetchBody($uid);
					$message = $this->mimeParser->parse($raw);
					$account = $this->resolveAccount($accounts, $message);
					if ($account === null) {
						$result['skipped']++;
						$this->logger->info('Leiterrunde IMAP: no account for mail "' . $message->subject . '" of ' . $message->from . ' – left unread');
						continue;
					}
					$parser = $this->parsers->get((string)$account->getParserKey() ?: 'generic');
					$parsed = $parser?->parse($message) ?? [];
					foreach ($parsed as $pt) {
						$res = $this->txService->add($account->getId(), $pt, 'imap', $message->body);
						if ($res['result'] === TransactionService::RESULT_DUPLICATE) {
							$result['duplicates']++;
						} else {
							$result['imported']++;
							if ($res['result'] === TransactionService::RESULT_MATCHED) {
								$result['matched']++;
							}
						}
					}
					$client->markSeen($uid);
				} catch (\Throwable $e) {
					$result['errors'][] = ['uid' => $uid, 'message' => $e->getMessage()];
					$this->logger->warning('Leiterrunde IMAP: Error at UID ' . $uid . ': ' . $e->getMessage());
				}
			}
		} finally {
			$client->logout();
		}
		$this->settings->set('imap_last_poll', (string)time());
		$this->setStatus($result);
		return $result;
	}

	private function connectFromSettings(): ImapClient {
		$host = $this->settings->get('imap_host');
		$user = $this->settings->get('imap_user');
		$pass = $this->settings->get('imap_password');
		if ($host === '' || $user === '' || $pass === '') {
			throw new ApiException('IMAP is not fully configured', 400);
		}
		$client = new ImapClient();
		try {
			$client->connect($host, $this->settings->getInt('imap_port', 993), $this->settings->get('imap_tls', 'ssl'));
			$client->login($user, $pass);
		} catch (\Throwable $e) {
			$this->setStatus([], $e->getMessage());
			throw new ApiException('IMAP connection failed: %s', 400, [$e->getMessage()]);
		}
		return $client;
	}

	private function resolveAccount(array $accounts, \OCA\LeiterrundeFinanzen\Service\BankMail\MailMessage $message): ?\OCA\LeiterrundeFinanzen\Db\Account {
		$haystack = mb_strtolower($message->from . "\n" . $message->subject . "\n" . $message->body);
		foreach ($accounts as $a) {
			$hint = trim((string)$a->getMailHint());
			if ($hint !== '' && str_contains($haystack, mb_strtolower($hint))) {
				return $a;
			}
		}
		return null;
	}

	private function setStatus(array $stats = [], ?string $message = null): void {
		$this->settings->set('imap_last_status', ImapStatus::encode($message === null, time(), $stats, $message));
	}
}
