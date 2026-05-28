# Drupal 7 to Drupal 11 Migration Kit

A drop-in migration module that provides ready-to-run migration YAML configs
and custom process plugins for the parts of a Drupal 7 → Drupal 11 migration
that the migrate_drupal core stack does not handle cleanly.

It is *not* a one-click upgrade. It is the boilerplate I have copied between
projects often enough to extract into a module.

## What's covered

- Users (with role mapping, signature, picture, password rehash note).
- Taxonomy vocabularies + terms (including parent relationships).
- Files (with optional public/private destination remap).
- Article and Basic page nodes.
- D7 `field_collection` → D11 `paragraphs`.
- D7 redirect module data → D11 redirect module.
- Custom process plugins for the awkward bits:
  - Split a single full name field into first/last.
  - Rewrite D7 inline media tokens and `<img>` references into D11
    `<drupal-media>` tags using the migrated media UUID.
  - D7 role id → D11 role machine name via configurable map.
  - D7 path alias lookup for redirect generation.
  - Ensure file URI lands in the right scheme (`public://` vs `private://`).

## What's NOT covered

The following things are deliberately out of scope — they are project-specific
or require manual review:

- Block layouts and block placement (D7 → D11 architecture differs).
- Panels / Panelizer layouts (use `layout_builder` manually).
- Features module exports (re-export as config in D11).
- Contrib modules with no D11 equivalent (audit + replace manually).
- Image style derivatives (regenerate post-migration).
- Search index data (rebuild after migration).
- Webform submissions (use `webform_migrate` separately).

## Prerequisites

- A Drupal 11 site, freshly installed, with the standard install profile or
  your custom profile.
- PHP 8.3 or newer.
- Access to the Drupal 7 source database and the D7 `sites/default/files`
  directory readable from the D11 server.
- `drush` 13.

## Installation

```bash
composer require nikolaj/d7-to-d11-migration-kit
drush en d7_to_d11_migrations
```

Add the D7 source database to `settings.php`:

```php
$databases['migrate_d7']['default'] = [
  'database' => 'd7_legacy',
  'username' => 'd7user',
  'password' => 'secret',
  'host' => '127.0.0.1',
  'driver' => 'mysql',
  'prefix' => '',
];
```

See `examples/settings.php.d7-source-snippet.php` for the annotated version.

## Running migrations

```bash
# Inspect status
drush ms --group=d7_to_d11_content

# Run everything in dependency order
drush mim --group=d7_to_d11_content

# Roll back if needed
drush mr --group=d7_to_d11_content
```

For a step-by-step guide see [`docs/running-migrations.md`](docs/running-migrations.md).

## Pitfalls (the short version)

Read the full list in [`docs/pitfalls.md`](docs/pitfalls.md). Highlights:

- **UID 1 conflict.** Drupal 11 already has uid 1 as admin. The user migration
  skips it; pre-existing role assignments must be done manually.
- **Passwords are rehashed.** D7 used a different hash; users must request a
  password reset after the cut-over. There is no clean way around this.
- **`public://` vs `private://`.** D7 `file_public_path` is often
  `sites/default/files`. If you change that in D11 the file migration needs the
  `source_base_path` constant updated accordingly.
- **Vocabularies that changed machine names.** Define a static_map in the
  taxonomy_term migration if the D7 vocabulary VID does not align with the D11
  machine name.
- **Filter formats.** D7 `filtered_html` is not the same thing as D11
  `basic_html`. Either run `d7_filter_format` from core first, or static_map
  the `format` column in your body processing.

## License

MIT — see `LICENSE`.
