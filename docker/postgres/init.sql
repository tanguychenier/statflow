-- =============================================================================
-- Statflow — PostgreSQL application database initialisation
--
-- Executed once at container first-start by the PostgreSQL Docker entrypoint
-- when placed in /docker-entrypoint-initdb.d/.
--
-- POSTGRES_DB / POSTGRES_USER / POSTGRES_PASSWORD (set in compose.yaml / .env)
-- create the superuser and default database automatically before this script
-- runs. The application connects as POSTGRES_USER; this script adds useful
-- extensions and default privileges for any future least-privilege role.
-- =============================================================================

-- Enable the pg_trgm extension used by Symfony's full-text search helpers
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Enable uuid-ossp for UUID generation in application code
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
