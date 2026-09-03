<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

/**
 * Splits a stream wrapper URI into its scheme and scheme-relative target.
 *
 * Shared by process plugins that need to reason about the `scheme://target`
 * shape of a D7 file URI, e.g. `public://images/photo.jpg`.
 */
trait SchemeTargetTrait {

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
