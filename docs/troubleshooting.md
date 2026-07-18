# Troubleshooting common migrate failures

A quick reference for the errors that actually surface mid-migration and the
exact `drush` command to get past each one. This is the runtime companion to
[`pitfalls.md`](pitfalls.md), which covers design-time gotchas rather than
error messages.

## 1. "Source database not found" (missing `migrate_d7` key)

**Symptom.** `drush ms` or `drush mim` fails immediately with something like:

```text
The specified database connection is not defined: migrate_d7
```

**Cause.** The migration group reads its source from `source.key: migrate_d7`
(see `config/install/migrate_plus.migration_group.d7_to_d11_content.yml`), so
`settings.php` must declare a matching `$databases['migrate_d7']['default']`
array. If it is absent — or you added it after the last cache rebuild — the
connection cannot be resolved.

**Fix.** Add the snippet from `examples/settings.php.d7-source-snippet.php`,
then confirm the key resolves and rebuild caches:

```bash
drush sql:query --database=migrate_d7 "SELECT COUNT(*) FROM node"
drush cache:rebuild
drush ms --group=d7_to_d11_content
```

## 2. Map vs. message table confusion

Each migration keeps two tracking tables in the **Drupal 11** database:

- `migrate_map_<id>` — maps each source id to its destination id plus a row
  hash used for change detection.
- `migrate_message_<id>` — per-row warnings and errors.

A migration that reports `imported` rows but "missing" content is almost always
a message-table problem, not a source problem. Read the messages before
touching anything:

```bash
drush migrate:messages d7_node_article   # alias: drush mmsg d7_node_article
```

Never truncate `migrate_map_*` by hand to "reset" a migration — that desyncs
the two tables. Use the supported commands, which keep them consistent:

```bash
drush migrate:reset-status d7_node_article   # clears a stuck "Importing" state
drush mr d7_node_article                      # rolls back map + messages
```

## 3. Re-running: `--update` vs. `--force`

`drush mim` only re-imports source rows whose hash changed. Two flags override
that behaviour:

- Re-import rows even when the source hash is unchanged (for example after you
  edited the process pipeline):

  ```bash
  drush mim d7_node_article --update
  ```

- Run a migration that Drupal considers already-run or that is blocked by an
  unmet optional dependency:

  ```bash
  drush mim d7_node_article --force
  ```

When a pipeline change must re-derive **every** field, a rollback followed by a
fresh import is cleaner than `--update`:

```bash
drush mr d7_node_article
drush mim d7_node_article
```

## 4. Highwater marks and rollback

Large source tables use a `high_water_property` so re-runs only scan rows newer
than the last watermark — which silently hides edits to older rows.

- Force a full re-scan that ignores the watermark:

  ```bash
  drush mim d7_node_article --update
  ```

- Roll a single migration back (this clears its map and message tables):

  ```bash
  drush mr d7_node_article
  ```

- Roll the whole group back before a clean final pass:

  ```bash
  drush mr --group=d7_to_d11_content
  ```

Roll dependents back **before** their dependencies — e.g. `drush mr
d7_node_article` before `drush mr d7_files` — otherwise the map rows the node
migration still needs are removed first.

## 5. "Allowed memory size exhausted"

Big file or node migrations can exhaust PHP's memory limit on a single large
batch.

- Cap the batch and feedback size so fewer rows are held in memory at once:

  ```bash
  drush mim d7_files --limit=500 --feedback=100
  ```

- Raise the CLI memory limit just for this run:

  ```bash
  php -d memory_limit=1024M vendor/bin/drush mim d7_files
  ```

- Import a huge table in slices, repeating until `drush ms` reports 0
  unprocessed rows:

  ```bash
  drush mim d7_files --limit=1000
  ```

## 6. File-path errors (`source_base_path`)

**Symptom.** The file migration logs `File '...' does not exist` (or copies
0 bytes) even though `drush ms` shows the rows as imported.

**Cause.** `source_base_path` (and `source_private_path`) in the migration
group point at a directory the Drupal 11 server cannot read, or the required
trailing slash is missing.

**Fix.** Point the constant at the absolute path of the D7
`sites/default/files/` directory **as the D11 server sees it** (trailing slash
required), rebuild caches, then re-run the file migration:

```bash
drush config:set migrate_plus.migration_group.d7_to_d11_content \
  shared_configuration.source.constants.source_base_path '/var/www/d7-legacy/sites/default/files/'
drush cache:rebuild
drush mr d7_files
drush mim d7_files
```

Spot-check that a file landed with a usable URI:

```bash
drush ev "print \Drupal\file\Entity\File::load(1)->getFileUri();"
```
