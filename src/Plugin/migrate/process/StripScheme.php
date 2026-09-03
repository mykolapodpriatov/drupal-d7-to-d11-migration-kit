<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Strips the stream wrapper scheme off a D7 file URI.
 *
 * Turns `public://sub/dir/name.txt` into `sub/dir/name.txt`. Values without a
 * `scheme://` prefix are returned unchanged. This is a pure value transform
 * with no side effects on the row, intended to be run as its own process
 * step so the result can be referenced by a later step via the destination
 * pseudo-property syntax, e.g. `@filepath_without_scheme`.
 *
 * @code
 * filepath_without_scheme:
 *   plugin: d7_to_d11_strip_scheme
 *   source: uri
 *
 * source_full_path:
 *   plugin: concat
 *   source:
 *     - constants/source_base_path
 *     - '@filepath_without_scheme'
 * @endcode
 */
#[MigrateProcess(
  id: 'd7_to_d11_strip_scheme',
  handle_multiples: FALSE,
)]
final class StripScheme extends ProcessPluginBase {

  use SchemeTargetTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    if (!is_string($value) || $value === '') {
      return '';
    }

    return $this->extractTarget($value);
  }

}
