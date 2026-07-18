#!/usr/bin/env bash
#
# validate-migrations.sh
#
# Statically validate the migration YAML wiring in migrations/*.yml without
# booting Drupal or touching a database, so it runs in CI in well under a
# second.
#
# For every migrations/*.yml it asserts that:
#   * the top-level `id:` equals the file name (minus the .yml extension);
#   * `migration_group:` equals the group id declared in
#     config/install/migrate_plus.migration_group.d7_to_d11_content.yml;
#   * every `migration_dependencies.required` entry resolves to a migration id
#     that actually exists in migrations/;
#   * every `migration_lookup` `migration:` value resolves to an existing id.
#
# yamllint is run over migrations/ and config/install/ when it is installed and
# skipped with a notice otherwise.
#
# Exit status: 0 when everything is wired correctly, 1 when any check fails.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

MIGRATIONS_DIR="${REPO_ROOT}/migrations"
CONFIG_DIR="${REPO_ROOT}/config/install"
GROUP_CONFIG="${CONFIG_DIR}/migrate_plus.migration_group.d7_to_d11_content.yml"

errors=0

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  errors=$((errors + 1))
}

# Print the value of a top-level (column 0) scalar key, first occurrence only.
top_scalar() {
  local key="$1" file="$2"
  awk -v k="$key" '
    index($0, k ":") == 1 {
      sub("^" k ":[[:space:]]*", "")
      sub(/[[:space:]]+$/, "")
      sub(/^["'\'']/, "")
      sub(/["'\'']$/, "")
      print
      exit
    }
  ' "$file"
}

# Print every `migration_lookup` `migration:` value in a file. The prefixed
# keys node_migration:/page_migration:/media_migration: are intentionally not
# matched because the anchor requires a bare `migration:` key.
lookup_migrations() {
  awk '
    /^[[:space:]]*migration:[[:space:]]/ {
      sub(/^[[:space:]]*migration:[[:space:]]*/, "")
      sub(/[[:space:]]+$/, "")
      sub(/^["'\'']/, "")
      sub(/["'\'']$/, "")
      print
    }
  ' "$1"
}

# Print every entry under the top-level migration_dependencies.required key.
# Inline empty mappings (`required: {  }`) and the sibling optional: list are
# both ignored.
required_deps() {
  awk '
    /^migration_dependencies:/ { in_md = 1; in_req = 0; next }
    in_md && /^[^[:space:]]/ { in_md = 0; in_req = 0 }
    in_md && /^[[:space:]]+required:/ {
      in_req = 1
      line = $0
      sub(/^[[:space:]]+required:[[:space:]]*/, "", line)
      if (line ~ /^[{[]/) { in_req = 0 }
      next
    }
    in_md && in_req && /^[[:space:]]+[A-Za-z_]+:/ { in_req = 0 }
    in_md && in_req && /^[[:space:]]+-[[:space:]]+/ {
      val = $0
      sub(/^[[:space:]]+-[[:space:]]+/, "", val)
      sub(/[[:space:]]+$/, "", val)
      sub(/^["'\'']/, "", val)
      sub(/["'\'']$/, "", val)
      print val
    }
  ' "$1"
}

# --- Gather the set of known migration ids. -------------------------------
shopt -s nullglob
migration_files=("$MIGRATIONS_DIR"/*.yml)
shopt -u nullglob

if [ "${#migration_files[@]}" -eq 0 ]; then
  fail "no migration YAML files found in ${MIGRATIONS_DIR}"
  printf '\nvalidate-migrations: %d error(s).\n' "$errors" >&2
  exit 1
fi

declare -a known_ids=()
for file in "${migration_files[@]}"; do
  known_ids+=("$(top_scalar id "$file")")
done

is_known_id() {
  local needle="$1" id
  for id in "${known_ids[@]}"; do
    [ "$id" = "$needle" ] && return 0
  done
  return 1
}

# --- Read the migration group id from the install config. -----------------
if [ ! -f "$GROUP_CONFIG" ]; then
  fail "migration group config not found: ${GROUP_CONFIG}"
  printf '\nvalidate-migrations: %d error(s).\n' "$errors" >&2
  exit 1
fi

group_id="$(top_scalar id "$GROUP_CONFIG")"
if [ -z "$group_id" ]; then
  fail "could not read group id from ${GROUP_CONFIG}"
fi

# --- Validate each migration. ---------------------------------------------
for file in "${migration_files[@]}"; do
  base="$(basename "$file" .yml)"
  rel="migrations/${base}.yml"

  id="$(top_scalar id "$file")"
  if [ "$id" != "$base" ]; then
    fail "${rel}: id '${id}' does not match file name '${base}'"
  fi

  group="$(top_scalar migration_group "$file")"
  if [ "$group" != "$group_id" ]; then
    fail "${rel}: migration_group '${group}' != group id '${group_id}'"
  fi

  while IFS= read -r dep; do
    [ -z "$dep" ] && continue
    if ! is_known_id "$dep"; then
      fail "${rel}: required dependency '${dep}' is not a known migration id"
    fi
  done < <(required_deps "$file")

  while IFS= read -r lookup; do
    [ -z "$lookup" ] && continue
    if ! is_known_id "$lookup"; then
      fail "${rel}: migration_lookup migration '${lookup}' is not a known migration id"
    fi
  done < <(lookup_migrations "$file")
done

# --- Optional: lint the YAML with yamllint. -------------------------------
YAMLLINT_CONFIG='{extends: default, rules: {document-start: disable, line-length: disable, braces: disable, brackets: disable, comments: disable, comments-indentation: disable, trailing-spaces: disable, new-line-at-end-of-file: disable, empty-lines: disable, truthy: disable}}'

if command -v yamllint >/dev/null 2>&1; then
  printf 'Running yamllint over migrations/ and config/install/ ...\n'
  if ! yamllint -d "$YAMLLINT_CONFIG" "$MIGRATIONS_DIR" "$CONFIG_DIR"; then
    fail "yamllint reported problems"
  fi
else
  printf 'NOTICE: yamllint not installed; skipping YAML lint.\n'
fi

# --- Summary. -------------------------------------------------------------
if [ "$errors" -gt 0 ]; then
  printf '\nvalidate-migrations: %d error(s).\n' "$errors" >&2
  exit 1
fi

printf 'validate-migrations: OK - %d migrations, group id "%s".\n' \
  "${#migration_files[@]}" "$group_id"
exit 0
