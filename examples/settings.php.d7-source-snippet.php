<?php

/**
 * @file
 * Annotated snippet for settings.php — D7 source database registration.
 *
 * Copy into your sites/default/settings.php (or settings.local.php) on the
 * Drupal 11 site BEFORE running any migration from this kit.
 *
 * The connection key (`migrate_d7`) must match the value used inside
 * `config/install/migrate_plus.migration_group.d7_to_d11_content.yml` and is what
 * `hook_requirements()` in d7_to_d11_migrations.install verifies.
 */

declare(strict_types=1);

// phpcs:disable

$databases['migrate_d7']['default'] = [
  // The MySQL/MariaDB database that contains the Drupal 7 schema.
  'database' => 'd7_legacy',
  'username' => 'd7user',
  'password' => 'secret',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  // D7 sites commonly used utf8 (3-byte). Force utf8mb4 so that emoji and
  // multibyte titles survive the migration. The source data is upgraded on
  // read; the D7 database itself is left alone.
  'collation' => 'utf8mb4_general_ci',
  'charset' => 'utf8mb4',
];

/*
 * D7 site variables that the migration kit consumes:
 *
 *  - file_public_path:    typically 'sites/default/files'
 *  - file_private_path:   typically empty, sometimes
 *                         'sites/default/private/files'
 *  - file_temporary_path: '/tmp' on most hosts
 *
 * If your D7 `file_public_path` is different, update the constant
 * `source_base_path` in:
 *
 *   config/install/migrate_plus.migration_group.d7_to_d11_content.yml
 *
 * to the absolute path that points at the D7 files directory as seen from
 * the D11 web server. Trailing slash is required.
 */
