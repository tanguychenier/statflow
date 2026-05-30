/**
 * Commitlint configuration for the Statflow monorepo.
 *
 * Enforces the Conventional Commits specification (https://conventionalcommits.org)
 * with a curated set of types suited to a full-stack analytics platform.
 *
 * Run via: docker run --rm -v $PWD:/repo ... commitlint --from HEAD~1
 */

/** @type {import('@commitlint/types').UserConfig} */
const config = {
  // Base ruleset: Conventional Commits v1.0.
  extends: ["@commitlint/config-conventional"],

  rules: {
    // ── Type ────────────────────────────────────────────────────────────────
    "type-enum": [
      2, // error
      "always",
      [
        "feat",     // new user-facing feature
        "fix",      // bug fix
        "perf",     // performance improvement (no behaviour change)
        "refactor", // code restructuring (no behaviour change)
        "style",    // formatting, whitespace — no logic change
        "test",     // adding or updating tests
        "docs",     // documentation only
        "build",    // build system, Docker, CI pipeline changes
        "ci",       // CI/CD workflow changes (.github/workflows)
        "chore",    // maintenance: dependency bumps, tooling config
        "revert",   // reverts a previous commit
        "security", // security fixes or hardening
        "i18n",     // internationalisation / translation
        "a11y",     // accessibility improvements
        "infra",    // infrastructure / deployment configuration
        "data",     // data migration or schema change
      ],
    ],

    // ── Scope ────────────────────────────────────────────────────────────────
    // Scope is optional but when present must match a known workspace or layer.
    "scope-enum": [
      1, // warning — don't fail; scopes evolve with the project
      "always",
      [
        // Workspaces
        "backend",
        "frontend",
        "tracker",
        // Shared / cross-cutting
        "docker",
        "ci",
        "deps",
        "docs",
        "config",
        "db",
        "migrations",
        "clickhouse",
        "postgres",
        "redis",
        "auth",
        "api",
        "sdk",
        "release",
      ],
    ],

    // ── Subject ──────────────────────────────────────────────────────────────
    "subject-case": [2, "always", "lower-case"],
    "subject-empty": [2, "never"],
    "subject-full-stop": [2, "never", "."],
    "subject-max-length": [2, "always", 100],

    // ── Header ───────────────────────────────────────────────────────────────
    "header-max-length": [2, "always", 120],

    // ── Body ─────────────────────────────────────────────────────────────────
    "body-leading-blank": [2, "always"],
    "body-max-line-length": [2, "always", 120],

    // ── Footer ───────────────────────────────────────────────────────────────
    "footer-leading-blank": [2, "always"],
    "footer-max-line-length": [2, "always", 120],
  },
};

export default config;
