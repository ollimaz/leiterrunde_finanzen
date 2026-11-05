<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!interface_exists(\OCP\IAppConfig::class)) {
	eval('namespace OCP; interface IAppConfig { public function getValueString(string $app, string $key, string $default = "", bool $lazy = false): string; public function setValueString(string $app, string $key, string $value, bool $sensitive = false): void; }');
	eval('namespace OCP; interface IGroupManager { public function groupExists(string $gid): bool; }');
	eval('namespace OCP\AppFramework; class App {}');
	eval('namespace OCP\AppFramework\Bootstrap; interface IBootstrap {} interface IRegistrationContext {} interface IBootContext {}');
	eval('namespace OCP\EventDispatcher; class Event {} interface IEventDispatcher { public function dispatchTyped(object $event): void; }');
	eval('namespace OCP; interface IUser { public function getUID(): string; }');
}


if (!interface_exists(\OCP\IL10N::class)) {
	eval('namespace OCP; interface IL10N { public function t(string $text, array $parameters = []): string; public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string; }');
}

if (!class_exists(\OCP\AppFramework\Db\QBMapper::class)) {
	eval('namespace OCP\\AppFramework\\Db; abstract class QBMapper {} abstract class Entity {}');
}

spl_autoload_register(static function (string $class): void {
	foreach (['OCA\\LeiterrundeFinanzen\\' => __DIR__ . '/../lib/', 'OCA\\Leiterrunde\\' => __DIR__ . '/../../leiterrunde/lib/'] as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
		}
	}
});
