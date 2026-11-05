<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service;

/**
 * Language-neutral IMAP last-run status. Stored as JSON
 * {"ok":bool,"ts":int,"mails":n,"imported":n,"duplicates":n,"skipped":n,"errors":n,"message":string|null}.
 * Legacy values were a German sentence prefixed by "Y-m-d H:i: "; those parse as ok unless
 * they start with "Fehler:" and are shown raw.
 */
class ImapStatus {
	public static function encode(bool $ok, int $ts, array $stats = [], ?string $message = null): string {
		return json_encode([
			'ok' => $ok,
			'ts' => $ts,
			'mails' => (int)($stats['fetched'] ?? 0),
			'imported' => (int)($stats['imported'] ?? 0),
			'duplicates' => (int)($stats['duplicates'] ?? 0),
			'skipped' => (int)($stats['skipped'] ?? 0),
			'errors' => is_array($stats['errors'] ?? null) ? count($stats['errors']) : (int)($stats['errors'] ?? 0),
			'message' => $message,
		]);
	}

	/** @return array{ok:bool,ts:int,mails:int,imported:int,duplicates:int,skipped:int,errors:int,message:?string,raw:?string}|null */
	public static function parse(?string $raw): ?array {
		if ($raw === null || $raw === '') {
			return null;
		}
		$d = json_decode($raw, true);
		if (is_array($d) && array_key_exists('ok', $d)) {
			return [
				'ok' => (bool)$d['ok'], 'ts' => (int)($d['ts'] ?? 0),
				'mails' => (int)($d['mails'] ?? 0), 'imported' => (int)($d['imported'] ?? 0),
				'duplicates' => (int)($d['duplicates'] ?? 0), 'skipped' => (int)($d['skipped'] ?? 0),
				'errors' => (int)($d['errors'] ?? 0), 'message' => $d['message'] ?? null, 'raw' => null,
			];
		}
		$ts = 0;
		$text = $raw;
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\s*(.*)$/s', $raw, $m)) {
			$ts = (int)strtotime($m[1]);
			$text = $m[2];
		}
		$failed = str_starts_with($text, 'Fehler:') || str_starts_with($text, 'Error:');
		return ['ok' => !$failed, 'ts' => $ts,
			'mails' => 0, 'imported' => 0, 'duplicates' => 0, 'skipped' => 0,
			'errors' => $failed ? 1 : 0, 'message' => null, 'raw' => $text];
	}
}
