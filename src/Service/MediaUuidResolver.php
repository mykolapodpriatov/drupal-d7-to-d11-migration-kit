<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Service;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Resolves a D11 media entity UUID from a D7 file fid.
 *
 * Process plugins use this to rewrite inline media references in body text
 * without having to load the migration plugin manager every time. Results
 * are cached per request to keep the hot path quick when a single body
 * field contains many embedded media references.
 */
final class MediaUuidResolver {

  /**
   * Per-request memoisation: fid => uuid|null.
   *
   * @var array<int, string|null>
   */
  private array $cache = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PluginManagerInterface $migrationManager,
  ) {}

  /**
   * Returns the UUID for the D11 media entity that maps to the given D7 fid.
   *
   * @param int $fid
   *   D7 managed file id.
   * @param string $migration_id
   *   The migration id that produced the media entities (typically
   *   `d7_file_to_media`).
   *
   * @return string|null
   *   UUID, or NULL when the mapping is not yet known.
   */
  public function resolve(int $fid, string $migration_id = 'd7_file_to_media'): ?string {
    if (array_key_exists($fid, $this->cache)) {
      return $this->cache[$fid];
    }

    $migration = $this->migrationManager->createInstance($migration_id);
    if (!$migration instanceof MigrationInterface) {
      return $this->cache[$fid] = NULL;
    }

    $destination_ids = $migration->getIdMap()->lookupDestinationIds(['fid' => $fid]);
    if (empty($destination_ids[0][0])) {
      return $this->cache[$fid] = NULL;
    }

    $entity = $this->entityTypeManager->getStorage('media')->load((int) $destination_ids[0][0]);
    return $this->cache[$fid] = $entity?->uuid();
  }

}
