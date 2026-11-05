<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\Imap;

use OCA\LeiterrundeFinanzen\Service\BankMail\MailMessage;

class MimeParser {
	public function parse(string $raw): MailMessage {
		[$head, $body] = $this->splitHeadBody($raw);
		$headers = $this->parseHeaders($head);
		$from = $this->decodeHeader($headers['from'] ?? '');
		$subject = $this->decodeHeader($headers['subject'] ?? '');
		$text = $this->extractText($headers, $body);
		return new MailMessage($from, $subject, $text);
	}

	private function splitHeadBody(string $raw): array {
		$pos = strpos($raw, "\r\n\r\n");
		if ($pos === false) {
			$pos = strpos($raw, "\n\n");
			return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 2)];
		}
		return [substr($raw, 0, $pos), substr($raw, $pos + 4)];
	}

	/** @return array<string,string> lowercase header name => raw value (unfolded) */
	private function parseHeaders(string $head): array {
		$headers = [];
		$current = null;
		foreach (preg_split('/\r?\n/', $head) as $line) {
			if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $current !== null) {
				$headers[$current] .= ' ' . trim($line);
				continue;
			}
			if (($p = strpos($line, ':')) !== false) {
				$current = strtolower(substr($line, 0, $p));
				$headers[$current] = trim(substr($line, $p + 1));
			}
		}
		return $headers;
	}

	public function decodeHeader(string $v): string {
		if ($v === '') {
			return '';
		}
		$decoded = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
		return $decoded === false ? $v : trim($decoded);
	}

	private function extractText(array $headers, string $body): string {
		$ctRaw = $headers['content-type'] ?? 'text/plain';
		$ct = strtolower($ctRaw);
		$cte = strtolower($headers['content-transfer-encoding'] ?? '');
		if (str_starts_with($ct, 'multipart/')) {
			if (!preg_match('/boundary="?([^";]+)"?/i', $ctRaw, $m)) {
				return '';
			}
			$boundary = $m[1];
			$plain = null;
			$html = null;
			foreach (explode('--' . $boundary, $body) as $part) {
				$part = trim($part);
				if ($part === '' || $part === '--') {
					continue;
				}
				[$ph, $pb] = $this->splitHeadBody($part);
				$pHeaders = $this->parseHeaders($ph);
				$pct = strtolower($pHeaders['content-type'] ?? 'text/plain');
				if (str_starts_with($pct, 'multipart/')) {
					$nested = $this->extractText($pHeaders, $pb);
					if ($plain === null && $nested !== '') {
						$plain = $nested;
					}
					continue;
				}
				$text = $this->decodeBody($pb, $pHeaders);
				if (str_starts_with($pct, 'text/plain') && $plain === null) {
					$plain = $text;
				} elseif (str_starts_with($pct, 'text/html') && $html === null) {
					$html = $text;
				}
			}
			if ($plain !== null) {
				return $plain;
			}
			return $html !== null ? $this->htmlToText($html) : '';
		}
		$text = $this->decodeBody($body, $headers);
		if (str_starts_with($ct, 'text/html')) {
			$text = $this->htmlToText($text);
		}
		return $text;
	}

	private function decodeBody(string $body, array $headers): string {
		$cte = strtolower($headers['content-transfer-encoding'] ?? '');
		$ct = $headers['content-type'] ?? '';
		if ($cte === 'base64') {
			$body = (string)base64_decode(preg_replace('/\s+/', '', $body), true);
		} elseif ($cte === 'quoted-printable') {
			$body = quoted_printable_decode($body);
		}
		$charset = 'UTF-8';
		if (preg_match('/charset="?([^";\s]+)"?/i', $ct, $m)) {
			$charset = strtoupper($m[1]);
		}
		if ($charset !== 'UTF-8' && $charset !== 'UTF8') {
			$converted = @mb_convert_encoding($body, 'UTF-8', $charset);
			if ($converted !== false) {
				$body = $converted;
			}
		}
		return trim($body);
	}

	private function htmlToText(string $html): string {
		$text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
		$text = preg_replace('/<\/(p|div|tr|li)>/i', "\n", $text) ?? $text;
		return trim((string)html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
}
