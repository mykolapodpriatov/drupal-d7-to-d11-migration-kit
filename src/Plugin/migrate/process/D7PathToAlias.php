<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a D7 internal path to its canonical D11 destination URI.
 *
 * Inputs like `node/42` are translated to `internal:/node/{new_nid}` by
 * looking the source nid up in the configured node migration(s). External
 * URLs and unrecognised paths fall back to the configured prefix or are
 * returned verbatim, so the plugin is safe to apply blanket-style across
 * the redirect table.
 *
 * @code
 * redirect_redirect:
 *   plugin: d7_to_d11_path_to_alias
 *   source: redirect
 *   node_migration: d7_node_article
 *   page_migration: d7_node_page
 *   fallback_internal_prefix: 'internal:/'
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "d7_to_d11_path_to_alias",
 *   handle_multiples = FALSE
 * )
 */
final class D7PathToAlias extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly PluginManagerInterface $migrationManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    $value = trim((string) $value);
    if ($value === '') {
      return '';
    }

    // External URLs are passed through.
    if (preg_match('#^(https?|mailto|tel)://?#i', $value) === 1) {
      return $value;
    }

    $fallback_prefix = $this->configuration['fallback_internal_prefix'] ?? 'internal:/';

    if (preg_match('#^node/(\d+)$#', $value, $m) === 1) {
      $source_nid = (int) $m[1];
      $candidates = [
        $this->configuration['node_migration'] ?? NULL,
        $this->configuration['page_migration'] ?? NULL,
      ];
      foreach (array_filter($candidates) as $migration_id) {
        $new_nid = $this->lookupDestinationId((string) $migration_id, $source_nid);
        if ($new_nid !== NULL) {
          return $fallback_prefix . 'node/' . $new_nid;
        }
      }
    }

    return $fallback_prefix . ltrim($value, '/');
  }

  /**
   * Looks up a destination id by source id for a given migration.
   */
  private function lookupDestinationId(string $migration_id, int $source_id): ?int {
    $migration = $this->migrationManager->createInstance($migration_id);
    if (!$migration instanceof MigrationInterface) {
      return NULL;
    }

    $destination_ids = $migration->getIdMap()->lookupDestinationIds(['nid' => $source_id]);
    if (empty($destination_ids[0][0])) {
      return NULL;
    }

    return (int) $destination_ids[0][0];
  }

}
