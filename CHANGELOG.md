# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-22

### Fixed

- Migrations are now actually discoverable. The migration definitions live in
  `migrations/` as core migration plugins (filenames match their ids), and the
  `d7_to_d11_content` group ships as a `migrate_plus` migration-group config
  entity under `config/install/`, so its shared `source.key` and file-path
  constants (`source_base_path`) are applied — previously the group sat inside
  `migrations/` and its shared configuration was never loaded.
- `d7_redirects`: strip a leading slash from the redirect source path with a
  regex `str_replace` (the previous `callback`/`ltrim` + `unpack_source` only
  trimmed whitespace and never removed the slash).

### Changed

- `drupal/paragraphs` and `drupal/redirect` are now hard dependencies — their
  migrations reference those modules' destination plugins.

### Removed

- Unused `MediaUuidResolver` and `D7PathAliasResolver` services (the latter also
  contained a fatal static `Connection::getConnection()` call). The process
  plugins resolve their lookups directly, so the service layer was dead code.

## [0.1.0] - 2026-05-28

### Added

- First public preview release: the `d7_to_d11_content` migration group,
  ready-made YAML migrations (users, taxonomy, files, nodes,
  field_collection → paragraphs, redirects), custom process/source plugins, and
  a `hook_requirements()` check validating the configured D7 source database key.

[Unreleased]: https://github.com/mykolapodpriatov/drupal-d7-to-d11-migration-kit/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/mykolapodpriatov/drupal-d7-to-d11-migration-kit/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/mykolapodpriatov/drupal-d7-to-d11-migration-kit/releases/tag/v0.1.0
