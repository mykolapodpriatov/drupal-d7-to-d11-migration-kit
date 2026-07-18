<?php

/**
 * @file
 * PHPUnit bootstrap for the fast, Drupal-free unit test suite.
 *
 * Registers PSR-4 prefixes for the module, its test namespace and the core
 * migrate component so the helper and process plugins under test autoload
 * without booting a Drupal kernel or service container.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$autoloader = require __DIR__ . '/../vendor/autoload.php';
assert($autoloader instanceof ClassLoader);

$migrate_src = __DIR__ . '/../vendor/drupal/core/modules/migrate/src';
$autoloader->addPsr4('Drupal\\d7_to_d11_migrations\\', __DIR__ . '/../src');
$autoloader->addPsr4('Drupal\\Tests\\d7_to_d11_migrations\\', __DIR__ . '/src');
$autoloader->addPsr4('Drupal\\migrate\\', $migrate_src);
