# Pitfalls and gotchas

A non-exhaustive list of the things that will catch you out on a real
Drupal 7 → Drupal 11 migration. Roughly ordered by how often they bite.

## 1. UID 1 collision

Drupal 11 already has uid 1 (the administrator created during install) before
the migration runs. The `d7_users` migration in this kit explicitly skips
uid 1; pre-existing role assignments and password handover for that single
account must be done manually after the fact.

The same applies in principle to uid 0 (anonymous), but `d7_user` already
excludes it.

## 2. Password rehash is one-way

Drupal 7 used a salted phpass scheme; Drupal 11 uses a different hash. The
migration copies the D7 hash into the D11 `users_field_data.pass` column with
`md5_passwords: true` so Drupal can rehash on first login. In practice every
user must perform a one-time password reset after cut-over.

If you cannot ask end users to do that, your only option is a custom
authentication module that accepts the old hash. There is no clean way around
this.

## 3. File system paths and stream wrappers

Two settings on the D7 side matter:

- `file_public_path` — almost always `sites/default/files`, but custom hosts
  sometimes override it.
- `file_private_path` — empty on many sites; on the ones where it is set,
  it usually points outside the docroot.

In this kit:

- `source_base_path` in `d7_to_d11_content.group.yml` must point at the D7
  public files directory **as it is reachable from the D11 server**. Trailing
  slash required.
- `EnsureFilePublic` lets you re-route per file. Some sites used `public://`
  for content that should have been private and vice-versa.

## 4. Field collection ordering and revisions

D7 `field_collection_item` has its own revision table. When a parent node is
revisioned, attached field_collection items can point at orphaned revisions.
The `d7_paragraphs_from_field_collection` migration imports the current
published revision only. If you need full revision history of the
field_collection items, run a second migration for `revision_id` separately
and stitch on the parent node side.

The parent node's paragraph reference field is *not* populated by the
paragraph migration. The parent node migration's process pipeline performs a
`migration_lookup` against `d7_paragraphs_from_field_collection` to wire up
`target_id` and `target_revision_id` on the parent.

## 5. Filter formats that no longer exist in D11

D7 commonly shipped `filtered_html` and `full_html`. D11 ships `basic_html`
and `full_html`. The article and page migrations static_map between them. If
your D7 site used custom formats (`my_custom_format`, `wysiwyg`, …), add them
to the static_map manually or run `d7_filter_format` from core first and
remove the static_map.

## 6. Image style derivatives

D7 image styles do not migrate automatically. After the file migration, run:

```bash
drush image:flush --all
```

…to make sure no half-baked D7 derivatives remain. Style configuration on the
D11 side is config-managed and outside this kit's scope.

## 7. View modes

D7 view modes (`teaser`, `full`, custom) survive only if you also migrate the
field display configuration. This kit imports content, not display. Run
`drush config:export` on the D11 site after manually configuring view modes;
do not try to migrate `field_ui_view_modes` directly.

## 8. Block layout (NOT migratable here)

D7 block placement uses the old `block` module's region table. D11 uses
either Block Layout (Olivero theme) or Layout Builder. Neither maps 1:1.
This kit deliberately does not attempt the migration; rebuild block layout
manually as part of the cut-over checklist.

## 9. Features module exports

D7 sites that used Features have config baked into exported modules. Do not
try to enable those modules on the D11 site — Features 7 is incompatible.
Re-export your config from the D11 site once content is migrated:

```bash
drush config:export
```

## 10. Contrib modules with no D11 equivalent

The big offenders, in our experience:

- `panels`, `panelizer` → Layout Builder, manual rebuild.
- `entityreference` → core `entity_reference`, automatic but watch widget
  settings.
- `webform` → there *is* a D11 webform, but submissions need
  `webform_migrate` separately.
- `flag` → has a D11 release, but flagging data must be migrated by hand.
- `rules` → ECA is the closest replacement; rules export is not portable.

Audit the D7 `system` table for enabled modules first; replace before
running content migrations.

## 11. Inline media in body text

The included `RewriteMediaEmbeds` plugin handles two patterns:

1. The D7 `media` module's JSON token: `[[{"type":"media","fid":"…"}]]`.
2. Plain `<img src="/sites/default/files/…">` referenced in body.

Anything more exotic (BUEditor inline tokens, custom shortcodes) needs a
project-specific extension of the plugin.

## 12. Idempotent re-runs

`drush mim` is idempotent — re-running re-imports only rows that changed
according to the source plugin's hash. Two caveats:

- Adding a process step does not invalidate existing rows; run
  `drush mr <id>` then `drush mim <id>` to re-run cleanly.
- Highwater marks (`high_water_property`) speed up large source tables but
  hide updates older than the watermark. Disable during the final cut-over
  pass.
