<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Helper;

/**
 * Static helpers for converting D7 link values into D11 link field syntax.
 */
final class LinkConverter {

  /**
   * Converts a D7 link URL into a D11 link field uri value.
   *
   * - Internal D7 paths (`node/123`, `taxonomy/term/9`) are returned in the
   *   `internal:/…` syntax that D11 `link` fields expect.
   * - External URLs are returned verbatim.
   * - Empty or `<front>` returns `internal:/`.
   *
   * @param string|null $url
   *   The raw D7 link URL.
   *
   * @return string
   *   A D11 link field-compatible URI string.
   */
  public static function toD11Uri(?string $url): string {
    $url = trim((string) $url);

    if ($url === '' || $url === '<front>') {
      return 'internal:/';
    }

    if (preg_match('#^(https?|mailto|tel):#i', $url) === 1) {
      return $url;
    }

    if (str_starts_with($url, '/')) {
      return 'internal:' . $url;
    }

    return 'internal:/' . ltrim($url, '/');
  }

}
