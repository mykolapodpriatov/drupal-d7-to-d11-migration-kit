<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps a list of D7 numeric role ids to D11 role machine names.
 *
 * Unlike `static_map` this plugin accepts an array on input (D7 stores
 * roles as a list keyed by rid) and returns an array of machine names
 * suitable for the D11 user entity `roles` field.
 *
 * @code
 * roles:
 *   plugin: d7_to_d11_role_map
 *   source: roles
 *   map:
 *     2: authenticated
 *     3: administrator
 *     4: editor
 *   default_value: authenticated
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "d7_to_d11_role_map",
 *   handle_multiples = TRUE
 * )
 */
final class D7RoleMap extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): array {
    if (!isset($this->configuration['map']) || !is_array($this->configuration['map'])) {
      throw new MigrateException('D7RoleMap requires a "map" configuration of D7 rid => D11 role machine name.');
    }

    $map = $this->configuration['map'];
    $default = $this->configuration['default_value'] ?? NULL;

    // D7 role data is typically an array keyed by rid. Accept either format.
    $rids = [];
    if (is_array($value)) {
      foreach ($value as $key => $name) {
        $rids[] = is_int($key) || ctype_digit((string) $key) ? (int) $key : (int) $name;
      }
    }
    elseif (is_scalar($value)) {
      $rids[] = (int) $value;
    }

    $out = [];
    foreach (array_unique($rids) as $rid) {
      if (isset($map[$rid])) {
        $out[] = $map[$rid];
      }
      elseif ($default !== NULL) {
        $out[] = $default;
      }
    }

    return array_values(array_unique($out));
  }

}
