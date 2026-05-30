# Security Policy

Copyright (C) 2026 Tanguy Chénier. All rights reserved.
Statflow is released under the GNU Affero General Public License v3.0 (AGPL-3.0).

## Supported Versions

Statflow has not yet had a stable release; security fixes target `main`.

| Version            | Supported         |
|--------------------|-------------------|
| main (pre-release) | Yes               |
| any tagged release | see release notes |

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues, pull requests, or discussions.** Doing so could put other users at risk before a fix is available.

### How to report

Send an e-mail to **<security@tansoftware.com>** with:

1. **Subject line**: `[SECURITY] Statflow — <brief description>`
2. **Description**: A clear, concise description of the vulnerability.
3. **Reproduction steps**: Step-by-step instructions to reproduce the issue, including environment details (OS, Docker version, Statflow version/commit SHA).
4. **Impact**: Your assessment of the potential impact (data exposure, privilege escalation, denial of service, etc.).
5. **Proof of concept** *(optional but helpful)*: Minimal code, request/response capture, or screenshots that demonstrate the issue.

Encrypted submissions are welcome. Our PGP public key is published at `https://tansoftware.com/.well-known/security.asc`.

### What to expect

| Timeline | Action |
|----------|--------|
| **48 hours** | Acknowledgement of your report. |
| **7 days** | Initial triage and severity assessment communicated to you. |
| **30 days** | Target for a patch or mitigation. Complex issues may take longer — we will keep you informed. |
| **After patch** | Coordinated public disclosure and credit in the release notes (unless you prefer to remain anonymous). |

We follow a **coordinated disclosure** model. We ask that you give us reasonable time to address the issue before making any public disclosure. In return, we commit to keeping you informed throughout the process and to crediting your work.

---

## Scope

Vulnerabilities in the following components are in scope:

- `apps/backend` — Symfony API, authentication, data ingestion endpoints
- `apps/frontend` — Vue SPA, session handling, data access
- `packages/tracker` — client-side tracking script (XSS, data exfiltration)
- Docker images and `compose.yaml` configuration shipped in this repository
- Data pipeline (Redis Streams → ClickHouse ingestion)

The following are **out of scope**:

- Vulnerabilities in third-party dependencies (report those upstream; we will update our dependencies promptly)
- Issues that only affect self-hosted deployments where the operator has intentionally weakened the default configuration
- Denial-of-service attacks requiring significant resources (e.g., volumetric floods)
- Social engineering

---

## Security Design Principles

Statflow is designed with privacy and security as first-class concerns:

- **Cookieless by default** — no persistent identifiers are stored on end-user devices.
- **No third-party calls** — the tracker script communicates only with the configured Statflow backend; no data is sent to external services.
- **Self-hostable** — operators retain full control over their data.
- **Minimal data collection** — only data required for analytics is collected and stored.
- **Input validation at the boundary** — all ingested events are validated and sanitised before reaching the analytical datastore.

---

## Acknowledgements

We thank all researchers who responsibly disclose vulnerabilities and help keep Statflow and its users safe.
