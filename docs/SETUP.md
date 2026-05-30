# Setup

Statflow is **container-first**: the only tools required on the host are Docker
and Docker Compose v2. A fresh clone reaches a fully working stack with one
command.

```bash
git clone https://github.com/<your-username>/statflow.git
cd statflow
cp .env.example .env   # review ports and passwords
make setup
```

When it finishes, the dashboard is at `http://localhost:${FRONTEND_PORT:-5173}`
and the API at `http://localhost:${BACKEND_PORT:-8000}`.

`make setup` is **idempotent** — running it again is safe and skips work that is
already complete. No manual secret, database, or schema steps are needed.

## What `make setup` does

| Step | Target | Action |
|------|--------|--------|
| 1 | `build` | Build the backend and frontend images. |
| 2 | `install` | Install Composer and pnpm dependencies inside the containers. |
| 3 | `secrets` | Generate the JWT keypair and `INSTALL_SECRET` (see below). |
| 4 | `up-data` | Start PostgreSQL, Redis and ClickHouse, then wait until all report healthy. |
| 5 | `db-migrate` | Run the application database migrations. |
| 6 | `db-test-setup` | Create the `statflow_test` database and run its migrations. |
| 7 | — | Start the backend and frontend services. |

The data-service ports, `BACKEND_PORT` and `FRONTEND_PORT` are read from `.env`
(template in `.env.example`).

## Secret provisioning

`scripts/generate-secrets.sh` runs inside the backend container (which ships
`openssl`) and writes to `apps/backend/.env.local`, a gitignored file
(`.env.*` in the root `.gitignore`). It provisions:

- **`JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY`** — an ES256 (ECDSA P-256) keypair used
  to sign and verify dashboard access tokens (ADR-0009), in PEM (PKCS#8) form.
- **`JWT_KEY_ID`** — the stable key identifier published in the JWKS endpoint.
- **`INSTALL_SECRET`** — the per-install HKDF root for cookieless visitor and
  session identity (ADR-0008).

The script **never overwrites** an existing value: each secret is written only
if it is absent, so re-running `make setup` (or `make secrets`) is a no-op once
the keys exist. To rotate a key, delete its line from `apps/backend/.env.local`
and re-run `make secrets`.

These secrets are **not** set in the root `.env` — that file is consumed only by
Docker Compose. Symfony reads the keys from `apps/backend/.env.local`.

## Databases

- **Application database** (`statflow`): created by the PostgreSQL container on
  first start; the schema is applied by Doctrine migrations in step 5.
- **Test database** (`statflow_test`): created and migrated in step 6. The
  `_test` suffix is applied automatically in the test environment (see
  `config/packages/doctrine.yaml`).
- **ClickHouse schema** (events, sessions and rollups): applied once by
  `docker/clickhouse/init.sql` when the ClickHouse data volume is first created.
  That file is the deployable mirror of the normative reference in
  `docs/data-model/clickhouse-schema.sql`.

## Production overlay

Production runs with the overlay applied:

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

The overlay **fails closed on CORS**: it does not inherit the permissive dev
default (`CORS_ALLOW_ORIGIN=*`). `CORS_ALLOW_ORIGIN` must be set to the explicit
dashboard origin (for example `https://stats.example.com`) or the stack refuses
to start. Dev remains permissive.

## Useful targets

| Target | Purpose |
|--------|---------|
| `make secrets` | Generate missing secrets only (idempotent). |
| `make up-data` | Start the data services and wait until healthy. |
| `make db-migrate` | Run application database migrations. |
| `make db-test-setup` | Create and migrate the `statflow_test` database. |
| `make up` / `make down` | Start / stop the full stack. |
| `make ps` | Show service status. |

Run `make help` for the full list.
