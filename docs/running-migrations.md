# Running the migrations

Step-by-step procedure to take a Drupal 7 site to Drupal 11 using this kit.

## 1. Prepare the D11 site

```bash
composer create-project drupal/recommended-project:^11 d11-site
cd d11-site
composer require drupal/migrate_plus:^6 drupal/migrate_tools:^6
composer require mykolapodpriatov/drupal-d7-to-d11-migration-kit
drush site:install --account-pass='change-me-locally'
drush en migrate migrate_drupal migrate_drupal_ui d7_to_d11_migrations
```

## 2. Configure the D7 source connection

Add the snippet from
`examples/settings.php.d7-source-snippet.php` into your
`sites/default/settings.php` (or, preferably, `settings.local.php`).

Verify it from CLI:

```bash
drush sql:query --database=migrate_d7 "SELECT COUNT(*) FROM node"
```

## 3. Point the file path constant at your D7 files directory

The source database key and file-path constants live in the
`d7_to_d11_content` **migration group** — a `migrate_plus` config entity
shipped at
`config/install/migrate_plus.migration_group.d7_to_d11_content.yml` and
imported when the module is installed. Set `source_base_path` to the absolute
path of the D7 `sites/default/files/` directory **as the D11 web server sees
it** (trailing slash required). The migrations themselves are code plugins
under `migrations/`, so editing the YAML there just needs a cache rebuild.

Update the already-installed group config and rebuild caches:

```bash
# Either edit it in the UI at /admin/structure/migrate, or set it from CLI:
drush config:set migrate_plus.migration_group.d7_to_d11_content \
  shared_configuration.source.constants.source_base_path '/var/www/d7-legacy/sites/default/files/'
drush cache:rebuild
```

## 4. Inspect status

```bash
drush ms --group=d7_to_d11_content
```

Expected output: every migration is `Idle, total > 0, imported 0`.

## 5. Run the pipeline

```bash
drush mim --group=d7_to_d11_content
```

Migrations execute in dependency order. To run a single migration:

```bash
drush mim d7_users
drush mim d7_taxonomy_vocabulary
drush mim d7_taxonomy_term
drush mim d7_files
drush mim d7_node_article
drush mim d7_node_page
drush mim d7_paragraphs_from_field_collection
drush mim d7_redirects
```

## 6. Re-runs (idempotent)

Re-running `drush mim` only re-imports rows whose source hash changed.
After editing process pipelines, force a full re-run for the affected
migration:

```bash
drush mr d7_node_article
drush mim d7_node_article
```

## 7. Rollback

Full rollback:

```bash
drush mr --group=d7_to_d11_content
```

Single migration rollback:

```bash
drush mr d7_node_article
```

## 8. Post-migration tasks

- `drush image:flush --all` — clear stale image style derivatives.
- `drush search-api:rebuild-tracker` — if Search API is configured.
- `drush config:export` — capture any config changes made during import.
- Manually rebuild block layout / Layout Builder placements.
- Notify users to reset their passwords.

## 9. Cut-over checklist

1. Run a full dry-run import on a staging copy.
2. Compare row counts (`drush ms --group=d7_to_d11_content`).
3. Spot-check 5–10 nodes by URL and by `drush ev "print Node::load(123)->toArray()"`.
4. Lock D7 to read-only.
5. Run `drush mim --group=d7_to_d11_content --update` for the final delta.
6. Flip DNS / front-end traffic.

## 10. Useful Drush commands

| Command | What it does |
|---|---|
| `drush ms` | Migration status across all groups. |
| `drush mim <id>` | Import a migration. |
| `drush mr <id>` | Roll back a migration. |
| `drush mmsg <id>` | Show messages logged during a migration. |
| `drush migrate:reset-status <id>` | Reset a stuck `Importing` status. |
| `drush mim <id> --update` | Re-import rows whose source rows changed. |
