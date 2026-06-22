# Extending the kit

This kit is intended as a starting point. Adding new content types is a
matter of copy-paste-edit.

## Adding a node migration

1. Copy `migrations/d7_node_article.yml` to
   `migrations/d7_node_<your_type>.yml`.
2. Change `id`, `label`, `source.node_type`, and `destination.default_bundle`
   to your bundle's machine name.
3. Replace the field-specific process steps with your own field mapping.
4. Re-import config:

   ```bash
   drush cache:rebuild
   drush ms --group=d7_to_d11_content
   ```

## Adding a paragraph bundle from a field_collection

1. Copy
   `migrations/d7_paragraphs_from_field_collection.yml`.
2. Change:
   - `id` (e.g. `d7_paragraphs_feature_block`).
   - `source.field_name` (the D7 field_collection field machine name).
   - `process.type.default_value` (the D11 paragraph bundle machine name).
3. Update the inner field mappings.
4. Register the parent node's reference field in the parent node migration:

   ```yaml
   'field_my_paragraphs':
     plugin: sub_process
     source: my_paragraphs_item_ids
     process:
       target_id:
         plugin: migration_lookup
         migration: d7_paragraphs_feature_block
         source: value
       target_revision_id:
         plugin: migration_lookup
         migration: d7_paragraphs_feature_block
         source: value
   ```

   The `my_paragraphs_item_ids` source column is provided by
   `D7NodeWithFieldCollection` when the node source plugin is configured
   with `field_collection_fields: [my_paragraphs]`.

## Naming conventions

| Layer | Pattern |
|---|---|
| Migration id | `d7_<entity>_<bundle>` (e.g. `d7_node_article`) |
| Migration YAML file | `migrations/<id>.yml` |
| Migration group | `d7_to_d11_content` for all content migrations |
| Process plugin id | `d7_to_d11_<verb>_<noun>` (e.g. `d7_to_d11_split_full_name`) |
| Process plugin class | `Drupal\d7_to_d11_migrations\Plugin\migrate\process\<CamelCase>` |
| Source plugin id | `d7_<noun_phrase>` |
| Source plugin class | `Drupal\d7_to_d11_migrations\Plugin\migrate\source\<CamelCase>` |

## Adding a process plugin

1. Create the class under `src/Plugin/migrate/process/`.
2. Annotate it with `@MigrateProcessPlugin(id = "d7_to_d11_<verb>")`.
3. Add a kernel test under `tests/src/Kernel/Process/`.
4. Document the configuration keys in the class docblock with a YAML example.

## Adding a source plugin

1. Create the class under `src/Plugin/migrate/source/`.
2. Annotate it with `@MigrateSource(id = "d7_<noun>")`.
3. Subclass an existing D7 source plugin where possible — re-implementing
   `query()` from scratch is rarely necessary and easy to get wrong.
