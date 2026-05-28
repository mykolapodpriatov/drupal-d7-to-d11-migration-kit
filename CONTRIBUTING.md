# Contributing

Thanks for taking the time to contribute. This module aims to be a pragmatic
toolbox for Drupal 7 to Drupal 11 migrations, so contributions that cover
real-world pain points are especially welcome.

## Reporting issues

When opening an issue, please include:

- The exact source content type / field type you are migrating.
- The migration ID and the `drush ms` row that misbehaves.
- A minimal reproducer (a few D7 nodes / terms / users).
- The Drupal 11 and `migrate_plus` / `migrate_tools` versions you use.

## Pull requests

1. Fork and create a feature branch from `main`.
2. Run the checks locally:

   ```bash
   vendor/bin/phpcs --standard=Drupal,DrupalPractice src tests
   vendor/bin/phpstan analyse -c phpstan.neon src tests
   vendor/bin/phpunit --group d7_to_d11_migrations
   ```

3. Keep commits focused. One logical change per commit.
4. Update `CHANGELOG.md` under `[Unreleased]`.

## Coding standards

- PHP 8.3+, strict types in every PHP file under `src/`.
- Follow `Drupal` and `DrupalPractice` PHPCS standards.
- Migration YAML follows the canonical `migrate_plus.migration.*` schema.
- Custom process plugins must have a kernel test under `tests/src/Kernel`.

## Commit messages

Use the imperative mood, present tense:

> Add D7 article node migration with media embed rewriting

Reference the issue id at the end where applicable.
