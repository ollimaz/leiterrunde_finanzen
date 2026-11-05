<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeFinanzen\Service\Imap;

/**
 * Minimaler IMAP-Client (kein ext-imap): LOGIN, SELECT, UID SEARCH/FETCH/STORE, LOGOUT.
 */
class ImapClient {
	/** @var resource|null */
	private $socket = null;
	private int $tagCounter = 0;
	private string $buffer = '';

	public function connect(string $host, int $port, string $tls, int $timeout = 15): void {
		$scheme = $tls === 'ssl' ? 'ssl' : 'tcp';
		$errno = 0;
		$errstr = '';
		$this->socket = @stream_socket_client(
			$scheme . '://' . $host . ':' . $port,
			$errno, $errstr, $timeout,
			STREAM_CLIENT_CONNECT
		);
		if ($this->socket === false) {
			throw new ImapException("Connection to {$host}:{$port} failed: {$errstr}");
		}
		stream_set_timeout($this->socket, $timeout);
		$greeting = $this->readLine();
		if (!str_starts_with($greeting, '* OK')) {
			throw new ImapException('Unexpected IMAP greeting: ' . $greeting);
		}
		if ($tls === 'starttls') {
			$this->command('STARTTLS');
			if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				throw new ImapException('STARTTLS failed');
			}
		}
	}

	public function login(string $user, string $password): void {
		$this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($password));
	}

	public function select(string $folder): void {
		$this->command('SELECT ' . $this->quote($folder));
	}

	/** @return int[] UIDs of UNSEEN mails */
	public function searchUnseen(): array {
		$lines = $this->command('UID SEARCH UNSEEN');
		foreach ($lines as $l) {
			if (preg_match('/^\* SEARCH(.*)$/i', $l, $m)) {
				return array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1])))));
			}
		}
		return [];
	}

	/** Fetch raw RFC822 body via BODY.PEEK[] */
	public function fetchBody(int $uid): string {
		$lines = $this->command('UID FETCH ' . $uid . ' BODY.PEEK[]');
		$raw = '';
		foreach ($lines as $l) {
			$raw .= $l . "\r\n";
		}
		// strip untagged wrapper lines: '* n FETCH (... BODY[] {len}' prefix and trailing ')'
		if (preg_match('/BODY(?:\.PEEK)?\[\]\s*\{\d+\}\r\n(.*?)\r?\n?\)\s*$/s', $raw, $m)) {
			return $m[1];
		}
		return $raw;
	}

	public function markSeen(int $uid): void {
		$this->command('UID STORE ' . $uid . ' +FLAGS (\\Seen)');
	}

	public function logout(): void {
		try {
			$this->command('LOGOUT');
		} catch (\Throwable) {
		}
		$this->close();
	}

	public function close(): void {
		if (is_resource($this->socket)) {
			fclose($this->socket);
		}
		$this->socket = null;
	}

	public function __destruct() {
		$this->close();
	}

	private function quote(string $s): string {
		if (strpbrk($s, "\r\n\0") !== false) {
			throw new ImapException('Invalid characters in IMAP argument');
		}
		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
	}

	/**
	 * Send tagged command, collect untagged lines until tagged completion.
	 * @return string[] untagged response lines (inkl. Literale)
	 * @throws ImapException
	 */
	private function command(string $cmd): array {
		if (!is_resource($this->socket)) {
			throw new ImapException('Not verbunden');
		}
		$this->tagCounter++;
		$tag = 'A' . str_pad((string)$this->tagCounter, 4, '0', STR_PAD_LEFT);
		$this->write($tag . ' ' . $cmd . "\r\n");
		$lines = [];
		while (true) {
			$line = $this->readLine();
			if ($line === null) {
				throw new ImapException('Connection lost at: ' . $cmd);
			}
			if (str_starts_with($line, $tag . ' ')) {
				if (!preg_match('/OK/i', substr($line, strlen($tag) + 1))) {
					throw new ImapException('IMAP-Fehler bei "' . $cmd . '": ' . $line);
				}
				return $lines;
			}
			$lines[] = $line;
			// literal continuation: line ends with {n} -> read n raw bytes appended to last line
			while (preg_match('/\{(\d+)\}$/', end($lines), $m)) {
				$literal = $this->readBytes((int)$m[1]);
				$lines[count($lines) - 1] .= "\r\n" . $literal;
				$next = $this->readLine();
				if ($next === null) {
					throw new ImapException('Connection lost in literal');
				}
				$lines[count($lines) - 1] .= $next;
				// the appended part may itself end with {n} (e.g. multiple literals) -> loop
			}
		}
	}

	private function write(string $data): void {
		$written = @fwrite($this->socket, $data);
		if ($written === false) {
			throw new ImapException('Schreiben auf IMAP-Socket failed');
		}
	}

	/** Read one CRLF-terminated line (respecting {n} literals embedded is handled by caller). */
	private function readLine(): ?string {
		$line = '';
		while (true) {
			if (($pos = strpos($this->buffer, "\n")) !== false) {
				$line = substr($this->buffer, 0, $pos + 1);
				$this->buffer = substr($this->buffer, $pos + 1);
				return rtrim($line, "\r\n");
			}
			$chunk = @fread($this->socket, 8192);
			if ($chunk === false || $chunk === '') {
				if ($this->buffer !== '') {
					$out = $this->buffer;
					$this->buffer = '';
					return rtrim($out, "\r\n");
				}
				return null;
			}
			$this->buffer .= $chunk;
		}
	}

	private function readBytes(int $n): string {
		$data = '';
		while (strlen($data) < $n) {
			if ($this->buffer !== '') {
				$take = substr($this->buffer, 0, $n - strlen($data));
				$data .= $take;
				$this->buffer = substr($this->buffer, strlen($take));
				continue;
			}
			$chunk = @fread($this->socket, min(8192, $n - strlen($data)));
			if ($chunk === false || $chunk === '') {
				throw new ImapException('Connection lost atm Lesen of ' . $n . ' Bytes');
			}
			$data .= $chunk;
		}
		return $data;
	}
}
