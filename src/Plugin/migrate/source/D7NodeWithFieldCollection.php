<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node as D7Node;

/**
 * Source plugin: D7 node + attached field_collection rows in one query.
 *
 * Drupal's stock `d7_node` source plugin fetches nodes and lets each field
 * plugin issue its own query for related rows. For sites with many
 * field_collection bundles this becomes the dominant cost of the migration.
 *
 * This plugin pre-joins a configurable list of field_collection field tables
 * so the resulting row arrives with all attached item ids materialised on
 * the row. Downstream processes can then `migration_lookup` against
 * `d7_paragraphs_from_field_collection` without an extra round-trip.
 *
 * Configuration:
 * - field_collection_fields: list of field_collection field machine names to
 *   pre-load. Each entry adds a column `<field_name>_item_ids` to the row
 *   containing an array of D7 field_collection_item ids.
 *
 * @code
 * source:
 *   plugin: d7_node_with_field_collection
 *   node_type: landing
 *   field_collection_fields:
 *     - field_call_to_action
 *     - field_feature_blocks
 * @endcode
 *
 * @MigrateSource(
 *   id = "d7_node_with_field_collection",
 *   source_module = "node"
 * )
 */
final class D7NodeWithFieldCollection extends D7Node {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row): bool {
    $continue = parent::prepareRow($row);
    if (!$continue) {
      return $continue;
    }

    $fields = $this->configuration['field_collection_fields'] ?? [];
    if (!is_array($fields) || $fields === []) {
      return $continue;
    }

    $nid = (int) $row->getSourceProperty('nid');
    $vid = (int) $row->getSourceProperty('vid');

    foreach ($fields as $field_name) {
      $row->setSourceProperty(
        $field_name . '_item_ids',
        $this->fetchFieldCollectionItemIds($field_name, $nid, $vid),
      );
    }

    return TRUE;
  }

  /**
   * Returns the D7 field_collection_item ids attached to a given node row.
   *
   * @param string $field_name
   *   D7 field_collection field machine name.
   * @param int $nid
   *   D7 node id.
   * @param int $vid
   *   D7 node revision id.
   *
   * @return array<int, int>
   *   List of field_collection_item ids.
   */
  private function fetchFieldCollectionItemIds(string $field_name, int $nid, int $vid): array {
    $table = 'field_data_' . $field_name;
    $value_column = $field_name . '_value';

    $query = $this->select($table, 'fd')
      ->fields('fd', [$value_column])
      ->condition('fd.entity_type', 'node')
      ->condition('fd.entity_id', $nid)
      ->condition('fd.revision_id', $vid)
      ->orderBy('fd.delta');

    return array_map('intval', $query->execute()->fetchCol() ?: []);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    $fields = parent::fields();
    foreach ($this->configuration['field_collection_fields'] ?? [] as $field_name) {
      $fields[$field_name . '_item_ids'] = $this->t('Attached @field field_collection item ids.', [
        '@field' => $field_name,
      ]);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'd7_node_with_field_collection';
  }

  /**
   * Convenience accessor for the parent migration (unused but documented).
   */
  public function getOwningMigration(): MigrationInterface {
    return $this->migration;
  }

}
