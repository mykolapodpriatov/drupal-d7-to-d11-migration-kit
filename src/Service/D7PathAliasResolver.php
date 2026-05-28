<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Service;

use Drupal\Core\Database\Connection;

/**
 * Reads path aliases from the D7 source `url_alias` table.
 *
 * The default migration_drupal `d7_url_alias` source already imports the
 * table, but during the redirect migration we sometimes need to resolve a
 * single source path on demand (e.g. when computing a fallback destination
 * for a 301 whose D7 target path was an alias rather than `node/N`).
 */
final class D7PathAliasResolver {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Resolves a D7 alias to its underlying system path.
   *
   * @param string $alias
   *   The alias as stored in D7 (e.g. `about-us/team`).
   *
   * @return string|null
   *   The system path (e.g. `node/42`) or NULL if no alias matches.
   */
  public function aliasToSource(string $alias): ?string {
    $alias = ltrim($alias, '/');
    if ($alias === '') {
      return NULL;
    }

    $key = 'migrate_d7';
    $connection = $this->database::getConnection('default', $key);
    if ($connection === NULL) {
      return NULL;
    }

    $source = $connection->select('url_alias', 'u')
      ->fields('u', ['source'])
      ->condition('alias', $alias)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $source === FALSE ? NULL : (string) $source;
  }

}
