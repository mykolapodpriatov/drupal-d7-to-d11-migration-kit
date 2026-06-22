<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Normalises a D7 file URI to the configured D11 destination scheme.
 *
 * Some D7 sites stored everything in `public://` even though parts of it
 * should have been private. This plugin lets the migration author re-route
 * each file based on its current scheme without losing the relative path.
 *
 * Configuration:
 * - public_destination: scheme to use when input is in public:// (default
 *   `public://`).
 * - private_destination: scheme to use when input is in private:// (default
 *   `private://`).
 *
 * @code
 * uri:
 *   plugin: d7_to_d11_ensure_file_public
 *   source: uri
 *   public_destination: 'public://'
 *   private_destination: 'private://'
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "d7_to_d11_ensure_file_public",
 *   handle_multiples = FALSE
 * )
 */
final class EnsureFilePublic extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    if (!is_string($value) || $value === '') {
      return '';
    }

    $public_destination = $this->configuration['public_destination'] ?? 'public://';
    $private_destination = $this->configuration['private_destination'] ?? 'private://';

    $scheme = $this->extractScheme($value);
    $target = $this->extractTarget($value);

    // Expose the path without scheme to following process steps that need
    // to build a real filesystem path (see `filepath_without_scheme` in the
    // file migration YAML).
    $row->setSourceProperty('filepath_without_scheme', $target);

    return match ($scheme) {
      'public' => $public_destination . $target,
      'private' => $private_destination . $target,
      'temporary' => $public_destination . $target,
      default => $value,
    };
  }

  /**
   * Returns the scheme portion of a stream wrapper URI.
   */
  private function extractScheme(string $uri): ?string {
    $position = strpos($uri, '://');
    return $position === FALSE ? NULL : substr($uri, 0, $position);
  }

  /**
   * Returns the part of a stream wrapper URI after the scheme.
   */
  private function extractTarget(string $uri): string {
    $position = strpos($uri, '://');
    return $position === FALSE ? $uri : substr($uri, $position + 3);
  }

}
