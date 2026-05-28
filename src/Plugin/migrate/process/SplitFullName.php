<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Splits a combined full-name string into first and last name parts.
 *
 * The split happens at the rightmost whitespace, so "Jean Paul Sartre"
 * becomes ["Jean Paul", "Sartre"]. Single-token inputs return only the
 * given name and an empty surname.
 *
 * @code
 * field_first_name:
 *   plugin: d7_to_d11_split_full_name
 *   source: full_name
 *   part: first
 *
 * field_last_name:
 *   plugin: d7_to_d11_split_full_name
 *   source: full_name
 *   part: last
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "d7_to_d11_split_full_name",
 *   handle_multiples = FALSE
 * )
 */
final class SplitFullName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    if (!is_string($value)) {
      return '';
    }

    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $part = $this->configuration['part'] ?? 'first';
    if (!in_array($part, ['first', 'last'], TRUE)) {
      $part = 'first';
    }

    // Use a multibyte-safe split on the rightmost whitespace.
    $position = $this->rightmostSpace($value);

    if ($position === NULL) {
      return $part === 'first' ? $value : '';
    }

    $first = mb_substr($value, 0, $position);
    $last = mb_substr($value, $position + 1);

    return $part === 'first' ? trim($first) : trim($last);
  }

  /**
   * Locates the rightmost whitespace character in a multibyte-safe way.
   */
  private function rightmostSpace(string $value): ?int {
    $length = mb_strlen($value);
    for ($i = $length - 1; $i >= 0; $i--) {
      $char = mb_substr($value, $i, 1);
      if (preg_match('/\s/u', $char) === 1) {
        return $i;
      }
    }

    return NULL;
  }

}
