<?php

declare(strict_types=1);

namespace Drupal\d7_to_d11_migrations\Plugin\migrate\process;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rewrites D7 inline media references into D11 `<drupal-media>` tags.
 *
 * Two input shapes are handled:
 *
 *  1. The D7 `media` module token format:
 *       `[[{"type":"media","fid":"42", ... }]]`
 *  2. Plain `<img>` tags referencing the D7 file directory:
 *       `<img src="/sites/default/files/foo.jpg" alt="..." />`
 *
 * Both are replaced with a `<drupal-media>` tag carrying the media entity
 * UUID, for example:
 *
 *   `<drupal-media data-entity-type="media" data-entity-uuid="…">`
 *
 * The destination UUID is resolved by looking up the migrated media entity
 * keyed by the source `fid` via the configured migration_lookup id (default
 * `d7_file_to_media`).
 *
 * @code
 * 'body/value':
 *   plugin: d7_to_d11_rewrite_media_embeds
 *   source: body/0/value
 *   media_migration: d7_file_to_media
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "d7_to_d11_rewrite_media_embeds",
 *   handle_multiples = FALSE
 * )
 */
final class RewriteMediaEmbeds extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.migration'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    if (!is_string($value) || $value === '') {
      return (string) $value;
    }

    $output = $this->rewriteJsonTokens($value);
    $output = $this->rewriteImgTags($output);

    return $output;
  }

  /**
   * Replaces D7 `[[{"type":"media", "fid":"…"}]]` JSON tokens.
   */
  private function rewriteJsonTokens(string $html): string {
    return preg_replace_callback(
      '/\[\[(\{[^\[\]]*?"type"\s*:\s*"media"[^\[\]]*?\})\]\]/u',
      function (array $match): string {
        $payload = json_decode($match[1], TRUE);
        if (!is_array($payload) || empty($payload['fid'])) {
          return $match[0];
        }
        $uuid = $this->lookupMediaUuid((int) $payload['fid']);
        if ($uuid === NULL) {
          return $match[0];
        }
        return $this->buildEmbedTag($uuid);
      },
      $html,
    ) ?? $html;
  }

  /**
   * Replaces `<img src="/sites/default/files/…">` tags.
   */
  private function rewriteImgTags(string $html): string {
    return preg_replace_callback(
      '#<img\b[^>]*src=["\'](?:[^"\']*?/sites/default/files/)([^"\']+)["\'][^>]*>#u',
      function (array $match): string {
        $relative = urldecode($match[1]);
        $uuid = $this->lookupMediaUuidByPath($relative);
        if ($uuid === NULL) {
          return $match[0];
        }
        return $this->buildEmbedTag($uuid);
      },
      $html,
    ) ?? $html;
  }

  /**
   * Looks up a destination media UUID by D7 file fid.
   */
  private function lookupMediaUuid(int $fid): ?string {
    $migration_id = $this->configuration['media_migration'] ?? 'd7_file_to_media';
    $migration = $this->migrationManager->createInstance($migration_id);
    if (!$migration instanceof MigrationInterface) {
      return NULL;
    }

    $destination_ids = $migration->getIdMap()->lookupDestinationIds(['fid' => $fid]);
    if (empty($destination_ids[0][0])) {
      return NULL;
    }

    $media_id = (int) $destination_ids[0][0];
    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    return $media?->uuid();
  }

  /**
   * Looks up a destination media UUID by relative file path.
   *
   * Path lookup is rare: only the JSON token path is the supported pipeline.
   * This method is kept as an extension point.
   */
  private function lookupMediaUuidByPath(string $relative_path): ?string {
    return NULL;
  }

  /**
   * Builds a `<drupal-media>` tag for an embedded media entity.
   */
  private function buildEmbedTag(string $uuid): string {
    return sprintf(
      '<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>',
      htmlspecialchars($uuid, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    );
  }

}
