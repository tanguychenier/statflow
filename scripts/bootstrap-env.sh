#!/usr/bin/env sh
# =============================================================================
# Statflow — root .env bootstrap (idempotent)
#
# A fresh checkout ships only .env.example. docker compose reads its credentials
# from .env, so without this step every "${POSTGRES_PASSWORD}" / "${REDIS_PASSWORD}"
# / "${CLICKHOUSE_PASSWORD}" / "${APP_SECRET}" expands to an empty string and the
# data stores boot misconfigured (Redis with no password, Postgres with no
# password) — the stack never reaches a healthy state.
#
# This script creates .env from .env.example on first run and replaces every
# CHANGE_ME* placeholder with a strong, randomly generated value. Each datastore
# and the backend DSN read the SAME variable, so a generated value stays
# consistent across the whole stack.
#
# Idempotent by design: if .env already exists it is left completely untouched,
# so it is safe to call from `make setup` on every run. The generated .env is
# gitignored and must never be committed.
#
# Pure POSIX sh. Uses openssl when present and falls back to /dev/urandom so it
# works on a host that only has Docker + a shell.
#
# This script is deliberately POSIX sh (it runs under the host /bin/sh, which may
# be dash), so `[ ]` is used over the bash-only `[[ ]]` (SC2292). random_token is
# a pure value producer invoked in command substitutions; the set -e suspension
# there (SC2311) is exactly the intended behaviour.
# shellcheck disable=SC2292,SC2311
# =============================================================================

set -eu

# Resolve the repository root from this script's location (it lives in scripts/).
unset CDPATH
SCRIPT_DIR=$(cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(cd -- "${SCRIPT_DIR}/.." && pwd)

ENV_FILE="${ROOT_DIR}/.env"
EXAMPLE_FILE="${ROOT_DIR}/.env.example"

log() {
  printf '  %s\n' "$1"
}

# Already provisioned — never clobber an existing .env (it may hold real values).
if [ -f "${ENV_FILE}" ]; then
  log ".env already present — leaving it untouched."
  exit 0
fi

if [ ! -f "${EXAMPLE_FILE}" ]; then
  echo "ERROR: ${EXAMPLE_FILE} not found — cannot bootstrap .env." >&2
  exit 1
fi

# Emit a URL-safe random token. openssl is preferred; /dev/urandom is the
# fallback so the script runs on a host without openssl. The result is restricted
# to [A-Za-z0-9] so it is safe inside a Doctrine/Redis DSN with no escaping.
random_token() {
  # $1 = number of output characters
  _len="$1"
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | cut -c1-"${_len}"
  else
    LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | dd bs="${_len}" count=1 2>/dev/null
  fi
}

# Replace `KEY=...placeholder...` with `KEY=<generated>` in the working copy of
# .env. Values never contain `/`, `&`, or `\`, so sed with a `|` delimiter is safe.
set_value() {
  _key="$1"
  _value="$2"
  sed "s|^${_key}=.*|${_key}=${_value}|" "${ENV_FILE}" >"${ENV_FILE}.tmp"
  mv "${ENV_FILE}.tmp" "${ENV_FILE}"
}

log "Creating .env from .env.example with generated local secrets…"
cp "${EXAMPLE_FILE}" "${ENV_FILE}"

# APP_SECRET is a 64-char hex string (Symfony convention: openssl rand -hex 32).
set_value APP_SECRET "$(random_token 64)"

# Datastore credentials — each is read by both the datastore container and the
# backend DSN, so a single generated value keeps the whole stack consistent.
set_value POSTGRES_PASSWORD   "$(random_token 32)"
set_value CLICKHOUSE_PASSWORD "$(random_token 32)"
set_value REDIS_PASSWORD      "$(random_token 32)"

# Grafana is only used with the observability profile, but give it a real
# password too so the default admin account is never blank.
set_value GRAFANA_ADMIN_PASSWORD "$(random_token 24)"

log ".env created. Credentials are random and local-only (gitignored)."
