# =============================================================================
# Statflow — root Makefile
#
# Single entry point for every developer workflow. Nothing runs on the host;
# every tool is invoked through Docker or docker compose.
#
# Usage:
#   make            — print this help
#   make <target>   — run a specific target
# =============================================================================

# ── Shell & safety ────────────────────────────────────────────────────────────
SHELL := /usr/bin/env bash
.SHELLFLAGS := -euo pipefail -c

# Abort if docker is not available on the host.
_ := $(shell command -v docker >/dev/null 2>&1 || { echo "ERROR: docker is required but not found in PATH." >&2; exit 1; })

# ── Project paths ─────────────────────────────────────────────────────────────
# ROOT is the absolute path of the directory that contains this Makefile.
# We use a shell call so that paths with spaces are handled correctly —
# Make's $(abspath)/$(realpath) functions split on whitespace.
ROOT := $(shell cd "$(dir $(lastword $(MAKEFILE_LIST)))" && pwd)

# ── Docker image versions (pin for reproducibility) ──────────────────────────
IMG_HADOLINT          := hadolint/hadolint:v2.12.0
IMG_YAMLLINT          := pipelinecomponents/yamllint:0.31.0
IMG_MARKDOWNLINT      := davidanson/markdownlint-cli2:v0.14.0
IMG_EDITORCONFIG      := mstruebing/editorconfig-checker:v3.0.3
IMG_GITLEAKS          := ghcr.io/gitleaks/gitleaks:v8.24.0
IMG_SHELLCHECK        := koalaman/shellcheck:v0.10.0
IMG_ACTIONLINT        := rhysd/actionlint:1.7.7
IMG_LSLINT            := lslintorg/ls-lint:1.11.2
IMG_SQLFLUFF          := sqlfluff/sqlfluff:3.3.1

# Shared docker run flags: mount the repo root read-only for linters.
DOCKER_LINT_FLAGS := --rm -v "$(ROOT):/repo:ro" -w /repo
# For tools that need write access (e.g. auto-fix):
DOCKER_FIX_FLAGS  := --rm -v "$(ROOT):/repo" -w /repo

# ── Compose & app config ──────────────────────────────────────────────────────
COMPOSE := docker compose

# Data services that must be healthy before migrations can run.
DATA_SERVICES := postgres redis clickhouse

# ── Colour helpers ────────────────────────────────────────────────────────────
BOLD   := \033[1m
RESET  := \033[0m
GREEN  := \033[0;32m
YELLOW := \033[0;33m
CYAN   := \033[0;36m

# =============================================================================
# DEFAULT TARGET — print help
# =============================================================================
.DEFAULT_GOAL := help

##@ General

.PHONY: help
help: ## Print this help message
	@awk 'BEGIN { \
	    FS = ":.*##"; \
	    printf "\n$(BOLD)$(CYAN)Statflow$(RESET) — developer command reference\n\n"; \
	    printf "$(BOLD)Usage:$(RESET)  make $(CYAN)<target>$(RESET)\n\n"; \
	} \
	/^##@/ { printf "\n$(BOLD)%s$(RESET)\n", substr($$0, 5) } \
	/^[a-zA-Z0-9_-]+:.*?##/ { \
	    printf "  $(CYAN)%-24s$(RESET) %s\n", $$1, $$2 \
	}' $(MAKEFILE_LIST)
	@echo ""

# =============================================================================
# DOCKER COMPOSE — lifecycle
# =============================================================================
##@ Docker Compose

.PHONY: setup
setup: ## One-command bootstrap from a fresh clone (idempotent — safe to re-run)
	@echo -e "$(BOLD)$(CYAN)[1/7] Building service images…$(RESET)"
	@$(COMPOSE) build
	@echo -e "$(BOLD)$(CYAN)[2/7] Installing dependencies (Composer + pnpm)…$(RESET)"
	@$(MAKE) install
	@echo -e "$(BOLD)$(CYAN)[3/7] Provisioning local secrets (JWT keypair + INSTALL_SECRET)…$(RESET)"
	@$(MAKE) secrets
	@echo -e "$(BOLD)$(CYAN)[4/7] Starting data services and waiting for healthy…$(RESET)"
	@$(MAKE) up-data
	@echo -e "$(BOLD)$(CYAN)[5/7] Running application database migrations…$(RESET)"
	@$(MAKE) db-migrate
	@echo -e "$(BOLD)$(CYAN)[6/7] Creating and migrating the test database (statflow_test)…$(RESET)"
	@$(MAKE) db-test-setup
	@echo -e "$(BOLD)$(CYAN)[7/7] Starting backend + frontend…$(RESET)"
	@$(COMPOSE) up -d backend frontend
	@echo ""
	@echo -e "$(GREEN)$(BOLD)Setup complete.$(RESET)"
	@# Source .env (if present) so the printed ports match what compose used.
	@set -a; [ -f "$(ROOT)/.env" ] && . "$(ROOT)/.env"; set +a; \
	    echo -e "  Backend : http://localhost:$${BACKEND_PORT:-8000}"; \
	    echo -e "  Frontend: http://localhost:$${FRONTEND_PORT:-5173}"

.PHONY: secrets
secrets: ## Generate the JWT keypair + INSTALL_SECRET into apps/backend/.env.local (idempotent)
	@$(COMPOSE) run --rm --no-deps -v "$(ROOT):/repo" -w /repo backend \
	    sh scripts/generate-secrets.sh

.PHONY: up
up: ## Start all services in the background
	$(COMPOSE) up -d

.PHONY: up-data
up-data: ## Start the data services (postgres/redis/clickhouse) and wait until healthy
	$(COMPOSE) up -d --wait $(DATA_SERVICES)

.PHONY: down
down: ## Stop and remove containers (volumes are preserved)
	$(COMPOSE) down

.PHONY: restart
restart: down up ## Restart all services

.PHONY: logs
logs: ## Follow logs for all services (Ctrl-C to exit)
	$(COMPOSE) logs -f

.PHONY: ps
ps: ## Show running service containers and their status
	$(COMPOSE) ps

.PHONY: build
build: ## (Re)build all service images
	$(COMPOSE) build

# =============================================================================
# DATABASE — migrations & test schema
# =============================================================================
##@ Database

.PHONY: db-migrate
db-migrate: ## Run application database migrations (requires data services up)
	$(COMPOSE) run --rm --no-deps backend \
	    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

.PHONY: db-test-setup
db-test-setup: ## Create the statflow_test database and run its migrations
	$(COMPOSE) run --rm --no-deps -e APP_ENV=test backend \
	    php bin/console doctrine:database:create --if-not-exists --env=test
	$(COMPOSE) run --rm --no-deps -e APP_ENV=test backend \
	    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

# =============================================================================
# DEPENDENCY INSTALLATION
# =============================================================================
##@ Dependencies

.PHONY: install
install: install-php install-node ## Install all dependencies (Composer + pnpm)

.PHONY: install-php
install-php: ## Run composer install inside the backend container
	docker compose run --rm backend \
	    composer install --no-interaction --prefer-dist --optimize-autoloader

.PHONY: install-node
install-node: ## Run pnpm install for all JS/TS workspaces inside the frontend container
	docker compose run --rm frontend \
	    pnpm install --frozen-lockfile

# =============================================================================
# SHELL ACCESS
# =============================================================================
##@ Shell access

.PHONY: sh-backend
sh-backend: ## Open an interactive shell in the backend (PHP) container
	docker compose run --rm backend bash

.PHONY: sh-frontend
sh-frontend: ## Open an interactive shell in the frontend (Node) container
	docker compose run --rm frontend sh

# =============================================================================
# LINT — polyglot suite
# =============================================================================
##@ Quality — lint

.PHONY: lint
lint: ## Run the full polyglot lint suite (read-only; no auto-fix)
	@# Run every linter and collect failures; don't abort on the first error.
	@overall=0; \
	for target in lint-dockerfiles lint-yaml lint-markdown lint-editorconfig \
	              lint-secrets lint-shell lint-actions lint-filenames lint-sql; do \
	    $(MAKE) "$$target" || overall=1; \
	done; \
	echo ""; \
	if [ "$$overall" -eq 0 ]; then \
	    echo -e "$(GREEN)$(BOLD)All linters passed.$(RESET)"; \
	else \
	    echo -e "$(YELLOW)$(BOLD)Lint suite completed with findings (see above).$(RESET)"; \
	    exit 1; \
	fi

.PHONY: lint-dockerfiles
lint-dockerfiles: ## Lint all Dockerfiles with hadolint
	@echo -e "\n$(BOLD)$(CYAN)[hadolint]$(RESET) Linting Dockerfiles…"
	@root='$(ROOT)'; \
	dockerfiles=$$(find "$$root" \
	    -not \( -path "*/vendor/*" -o -path "*/node_modules/*" -o -path "*/.git/*" -o -path "*/.pnpm-store/*" \) \
	    \( -name "Dockerfile" -o -name "Dockerfile.*" -o -name "*.dockerfile" \) \
	    2>/dev/null); \
	if [ -z "$$dockerfiles" ]; then \
	    echo "  No Dockerfiles found — skipping."; \
	else \
	    # Map host paths to /repo/* container paths for each file found. \
	    container_paths=$$(echo "$$dockerfiles" | sed "s|$$root/|/repo/|g"); \
	    docker run $(DOCKER_LINT_FLAGS) $(IMG_HADOLINT) \
	        hadolint --config /repo/.hadolint.yaml $$container_paths; \
	fi

.PHONY: lint-yaml
lint-yaml: ## Lint all YAML files with yamllint
	@echo -e "\n$(BOLD)$(CYAN)[yamllint]$(RESET) Linting YAML files…"
	docker run $(DOCKER_LINT_FLAGS) $(IMG_YAMLLINT) \
	    yamllint -c /repo/.yamllint.yaml .

.PHONY: lint-markdown
lint-markdown: ## Lint all Markdown files with markdownlint-cli2
	@echo -e "\n$(BOLD)$(CYAN)[markdownlint]$(RESET) Linting Markdown files…"
	docker run $(DOCKER_LINT_FLAGS) $(IMG_MARKDOWNLINT) \
	    "**/*.md" "**/*.mdx" \
	    "!**/node_modules/**" "!**/vendor/**" "!**/dist/**" "!**/build/**" "!**/.pnpm-store/**" "!**/coverage/**" \
	    --config /repo/.markdownlint.jsonc

.PHONY: lint-editorconfig
lint-editorconfig: ## Verify EditorConfig compliance with editorconfig-checker
	@echo -e "\n$(BOLD)$(CYAN)[editorconfig-checker]$(RESET) Checking EditorConfig compliance…"
	docker run $(DOCKER_LINT_FLAGS) $(IMG_EDITORCONFIG) \
	    ec -config /repo/.editorconfig-checker.json

.PHONY: lint-secrets
lint-secrets: ## Scan for leaked secrets with gitleaks
	@echo -e "\n$(BOLD)$(CYAN)[gitleaks]$(RESET) Scanning for secrets…"
	docker run --rm -v "$(ROOT):/repo" -w /repo $(IMG_GITLEAKS) \
	    detect --config /repo/.gitleaks.toml --source /repo --no-git \
	    --redact --exit-code 1

.PHONY: lint-shell
lint-shell: ## Lint shell scripts with shellcheck
	@echo -e "\n$(BOLD)$(CYAN)[shellcheck]$(RESET) Linting shell scripts…"
	@root='$(ROOT)'; \
	scripts=$$(find "$$root" \
	    -not \( -path "*/vendor/*" -o -path "*/node_modules/*" -o -path "*/.git/*" -o -path "*/.pnpm-store/*" \) \
	    \( -name "*.sh" -o -name "*.bash" \) \
	    2>/dev/null); \
	if [ -z "$$scripts" ]; then \
	    echo "  No shell scripts found — skipping."; \
	else \
	    container_paths=$$(echo "$$scripts" | sed "s|$$root/|/repo/|g"); \
	    # The image entrypoint is already `shellcheck`; pass only its arguments. \
	    docker run $(DOCKER_LINT_FLAGS) \
	        $(IMG_SHELLCHECK) --rcfile=/repo/.shellcheckrc $$container_paths; \
	fi

.PHONY: lint-actions
lint-actions: ## Lint GitHub Actions workflow files with actionlint
	@echo -e "\n$(BOLD)$(CYAN)[actionlint]$(RESET) Linting GitHub Actions workflows…"
	@root='$(ROOT)'; \
	workflow_files=$$(find "$$root/.github/workflows" -name "*.yml" -o -name "*.yaml" 2>/dev/null); \
	if [ -z "$$workflow_files" ]; then \
	    echo "  No .github/workflows files found — skipping."; \
	else \
	    # Pass files explicitly; actionlint's auto-discovery requires a .git root. \
	    container_paths=$$(echo "$$workflow_files" | sed "s|$$root/|/repo/|g"); \
	    docker run $(DOCKER_LINT_FLAGS) $(IMG_ACTIONLINT) \
	        $$container_paths; \
	fi

.PHONY: lint-filenames
lint-filenames: ## Enforce filename conventions with ls-lint
	@echo -e "\n$(BOLD)$(CYAN)[ls-lint]$(RESET) Checking filename conventions…"
	docker run $(DOCKER_LINT_FLAGS) $(IMG_LSLINT) \
	    /ls-lint --config /repo/.ls-lint.yml

.PHONY: lint-sql
lint-sql: ## Lint SQL migration files with sqlfluff
	@echo -e "\n$(BOLD)$(CYAN)[sqlfluff]$(RESET) Linting SQL files…"
	@root='$(ROOT)'; \
	# Lint only SQL files within app workspaces (apps/ + packages/). \
	# docker/*/init.sql files use mixed dialects and are owned by other agents. \
	sql_files=$$(find "$$root/apps" "$$root/packages" \
	    -name "*.sql" 2>/dev/null); \
	if [ -z "$$sql_files" ]; then \
	    echo "  No SQL files in apps/ or packages/ — skipping."; \
	else \
	    container_paths=$$(echo "$$sql_files" | sed "s|$$root/|/repo/|g"); \
	    # The sqlfluff image entrypoint is already 'sqlfluff'. \
	    docker run $(DOCKER_LINT_FLAGS) $(IMG_SQLFLUFF) \
	        lint --config /repo/.sqlfluff $$container_paths; \
	fi

# =============================================================================
# FIX — auto-fix where possible
# =============================================================================
##@ Quality — auto-fix

.PHONY: fix
fix: ## Auto-fix lint findings where possible (markdownlint, sqlfluff)
fix: fix-markdown fix-sql
	@echo ""
	@echo -e "$(GREEN)$(BOLD)Auto-fix pass complete.$(RESET) Review changes before committing."

.PHONY: fix-markdown
fix-markdown: ## Auto-fix Markdown issues with markdownlint-cli2 --fix
	@echo -e "\n$(BOLD)$(CYAN)[markdownlint --fix]$(RESET) Fixing Markdown…"
	docker run $(DOCKER_FIX_FLAGS) $(IMG_MARKDOWNLINT) \
	    "**/*.md" "**/*.mdx" \
	    --config /repo/.markdownlint.jsonc \
	    --fix \
	    "#node_modules" "#vendor" "#dist" "#build"

.PHONY: fix-sql
fix-sql: ## Auto-fix SQL style issues with sqlfluff fix
	@echo -e "\n$(BOLD)$(CYAN)[sqlfluff fix]$(RESET) Fixing SQL…"
	@root='$(ROOT)'; \
	sql_files=$$(find "$$root" \
	    -not \( -path "*/vendor/*" -o -path "*/node_modules/*" -o -path "*/.git/*" -o -path "*/.pnpm-store/*" \) \
	    -name "*.sql" 2>/dev/null); \
	if [ -z "$$sql_files" ]; then \
	    echo "  No SQL files found — skipping."; \
	else \
	    container_paths=$$(echo "$$sql_files" | sed "s|$$root/|/repo/|g"); \
	    docker run $(DOCKER_FIX_FLAGS) $(IMG_SQLFLUFF) \
	        fix --config /repo/.sqlfluff $$container_paths; \
	fi

.PHONY: lint-frontend
lint-frontend: ## Lint the frontend workspace (ESLint + type-check)
	@echo -e "\n$(BOLD)$(CYAN)[ESLint]$(RESET) Linting frontend workspace…"
	docker compose run --rm frontend \
	    pnpm --filter @statflow/frontend lint

# =============================================================================
# STATIC ANALYSIS & TESTS
# =============================================================================
##@ Quality — static analysis & tests

.PHONY: stan
stan: ## Run PHPStan static analysis on the backend
	@echo -e "\n$(BOLD)$(CYAN)[PHPStan]$(RESET) Running static analysis…"
	docker compose run --rm backend \
	    php vendor/bin/phpstan analyse --no-progress --memory-limit=1G

.PHONY: rector
rector: ## Run Rector PHP refactoring/upgrade tool (dry-run)
	@echo -e "\n$(BOLD)$(CYAN)[Rector]$(RESET) Dry-run mode — no files changed…"
	docker compose run --rm backend \
	    php vendor/bin/rector process --dry-run --no-progress-bar

.PHONY: rector-fix
rector-fix: ## Apply Rector transformations (writes to disk)
	@echo -e "\n$(BOLD)$(CYAN)[Rector --fix]$(RESET) Applying transformations…"
	docker compose run --rm backend \
	    php vendor/bin/rector process --no-progress-bar

.PHONY: test
test: test-backend test-frontend test-tracker ## Run the full test suite (backend + frontend + tracker)

.PHONY: test-backend
test-backend: ## Run PHPUnit tests inside the backend container
	@echo -e "\n$(BOLD)$(CYAN)[PHPUnit]$(RESET) Running backend tests…"
	docker compose run --rm backend \
	    php vendor/bin/phpunit --testdox --colors=always

.PHONY: test-frontend
test-frontend: ## Run Vitest for the frontend workspace
	@echo -e "\n$(BOLD)$(CYAN)[Vitest]$(RESET) Running frontend tests…"
	docker compose run --rm frontend \
	    pnpm --filter @statflow/frontend test --run

.PHONY: test-tracker
test-tracker: ## Run Vitest for the tracker package
	@echo -e "\n$(BOLD)$(CYAN)[Vitest]$(RESET) Running tracker tests…"
	docker compose run --rm frontend \
	    pnpm --filter @statflow/tracker test --run

# =============================================================================
# CI — full quality gate, run LOCALLY (no cloud runner, no billing)
# =============================================================================
##@ CI (local)

.PHONY: ci
ci: ## Run the FULL quality gate on this machine — the project's CI (no GitHub Actions)
	@echo -e "$(BOLD)$(CYAN)[CI 1/6] Polyglot repo lint…$(RESET)"
	@$(MAKE) lint
	@echo -e "$(BOLD)$(CYAN)[CI 2/6] Data services + test database…$(RESET)"
	@$(MAKE) up-data
	@$(MAKE) db-test-setup
	@echo -e "$(BOLD)$(CYAN)[CI 3/6] Backend — ECS, PHPStan, Rector, Deptrac…$(RESET)"
	$(COMPOSE) run --rm --no-deps backend sh -lc 'vendor/bin/ecs check --no-progress-bar && vendor/bin/phpstan analyse --no-progress --memory-limit=1G && vendor/bin/rector process --dry-run && vendor/bin/deptrac analyse --no-progress'
	@echo -e "$(BOLD)$(CYAN)[CI 4/6] Backend — PHPUnit (unit + integration)…$(RESET)"
	$(COMPOSE) run --rm backend vendor/bin/phpunit --testsuite=unit
	$(COMPOSE) run --rm -e APP_ENV=test backend vendor/bin/phpunit --testsuite=integration
	@echo -e "$(BOLD)$(CYAN)[CI 5/6] Frontend — typecheck, lint, tests, build…$(RESET)"
	$(COMPOSE) run --rm --no-deps frontend sh -lc 'pnpm --filter @statflow/frontend typecheck && pnpm --filter @statflow/frontend lint && pnpm --filter @statflow/frontend test && pnpm --filter @statflow/frontend build'
	@echo -e "$(BOLD)$(CYAN)[CI 6/6] Tracker — lint, tests, build, size budget…$(RESET)"
	$(COMPOSE) run --rm --no-deps frontend sh -lc 'pnpm --filter @statflow/tracker lint && pnpm --filter @statflow/tracker test && pnpm --filter @statflow/tracker build && pnpm --filter @statflow/tracker size'
	@echo -e "\n$(GREEN)$(BOLD)[CI] All gates passed locally. ✔$(RESET)"

# =============================================================================
# MAINTENANCE
# =============================================================================
##@ Maintenance

.PHONY: clean
clean: ## Remove project build artifacts and caches (project-scoped Docker resources only)
	@echo -e "$(YELLOW)Removing build artifacts and caches…$(RESET)"
	@rm -rf \
	    "$(ROOT)/apps/frontend/dist" \
	    "$(ROOT)/apps/frontend/.vite" \
	    "$(ROOT)/packages/tracker/dist" \
	    "$(ROOT)/coverage" \
	    "$(ROOT)/.php_cs.cache" \
	    "$(ROOT)/.php-cs-fixer.cache" \
	    "$(ROOT)/apps/backend/var/cache" \
	    "$(ROOT)/apps/backend/var/log"
	@echo -e "$(YELLOW)Removing Statflow compose services and local images…$(RESET)"
	docker compose down --rmi local --volumes --remove-orphans
	@echo -e "$(GREEN)Clean complete.$(RESET)"
