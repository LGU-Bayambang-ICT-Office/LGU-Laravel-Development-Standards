# AI Agent Instructions & Architecture Guide: LGU Laravel Development Standards

This repository is maintained by the **Information and Communications Technology Office (ICTO), Municipality of Bayambang** under standard **LGU-ICTO-STD-2026-V1**.

AI agents (Antigravity, Claude Code, Cursor, Copilot, Codex, Windsurf) working in this repository must adhere to the rules, architectural patterns, and runbooks defined below.

---

## 🏛️ Core Engineering Invariants

1. **Dual-Identifier Standard:**
   - Every domain database table and Eloquent model MUST have `BIGINT UNSIGNED id` primary key.
   - Every public or browser-facing route MUST bind via `uuid` using the `HasRouteUuid` trait. Integer IDs are never exposed in URLs.
   - Exemption: Intermediate pivot tables and append-only sub-line items without independent lifecycles.

2. **Balanced Database Query Architecture & RAW SQL Limitations:**
   - Default to Eloquent ORM. Never place raw SQL queries in Livewire components or Blade views.
   - Restrict RAW SQL (`DB::raw`, `whereRaw`, etc.) to backend Actions or Services for critical needs (aggregations, window functions, row locks).
   - 100% PDO parameter bindings (`?` or named parameters). Direct variable interpolation or concatenation is strictly prohibited.
   - Always preserve office-level tenancy scoping (`office_id`).

3. **Data Privacy (RA 10173 DPA) & Cryptography:**
   - Sensitive Personal Information (SPI) attributes must use the `EncryptedPii` cast (AES-256-CBC) with dedicated `PII_ENCRYPTION_KEY`.
   - Companion `_blind_index` columns with `HasPiiHashing::hashPii()` must be used for exact-match SQL search.

4. **COA Financial Ledger Immutability & Auditability:**
   - Destructive SQL `DELETE` or `TRUNCATE` is strictly forbidden on financial, tax, or voucher tables.
   - All state mutations must trigger continuous cryptographic SHA-256 hash chaining via `AuditActivityObserver`.
   - Audit activities are sealed via database triggers (`freeze_audit_logs_trigger.sql`).

5. **MVC vs. Livewire Separation:**
   - Comprehensive record reviews require dedicated `/{resource}/{uuid}` show routes.
   - Livewire modals are strictly for confirmations or forms with $\le 3$ inputs.
   - Official printable forms and PDF streams belong exclusively in dedicated single-action Controllers.

---

## 🤖 LGU AI Skills Suite (`.agents/skills/`)

The repository includes a triad of autonomous AI agent skills designed to work in conjunction with **Laravel Boost** and the **Model Context Protocol (MCP)**:

| Skill | Directory | Primary Role & Capabilities |
| :--- | :--- | :--- |
| **`lgu-code-author`** | `.agents/skills/lgu-code-author` | **Code Generation:** Architectural recipes for migrations (Dual-ID), models (`EncryptedPii` + blind index), Action/Service boundaries, `afterCommit` event safety, Segregation of Duties policies, and dedicated MVC routes. |
| **`lgu-audit-reviewer`** | `.agents/skills/lgu-audit-reviewer` | **Audit & Reporting:** Executes the compliance scanner via MCP or CLI, conducts semantic deep-dives on non-compliant files, and generates an executive Markdown audit report with ready-to-apply code replacement diffs. |
| **`lgu-standards-maintainer`** | `.agents/skills/lgu-standards-maintainer` | **Regulatory Curation:** Researches future OWASP Top 10 editions and NPC Data Privacy issuances online, confirms invariant mappings interactively, and updates mapping matrices and seed catalogs. |

### Progressive Execution Layer:
1. **With Laravel Boost / MCP Server:** Use `execute_artisan` (e.g. `lgu:audit --format=json --strict`), `get_database_schema`, and `get_routes`.
2. **Standard Local Shell:** Run `php artisan lgu:audit --format=json --strict` via terminal.
3. **Pure Agent Static Analysis:** When PHP CLI is absent, inspect workspace source files directly against the invariant rule patterns documented in the skills.

To publish these skills into a host Laravel application:
```bash
php artisan lgu:install --ai-skills
```

---

## 🛡️ AI Agent Runbook: Updating the Package for Future OWASP Top 10 Versions

When instructed to update this package for a new OWASP Top 10 revision (e.g., **OWASP Top 10:2028** or **OWASP Top 10:2029**), follow these exact 5 steps:

### Step 1: Update the Mapping Matrix (`src/Security/SecurityStandardsMapper.php`)
1. Update `public const DEFAULT_OWASP_VERSION = '<YEAR>';` (e.g., `'2029'`).
2. Update `public const ACTIVE_OWASP_LABEL = 'OWASP Top 10:<YEAR> (Active Version)';`.
3. In `getFrameworks()`, add the new framework entry for `'<YEAR>'`:
   - Map each new category (`A01:<YEAR>` through `A10:<YEAR>`) to the underlying Invariant Rule IDs:
     - `SEC-MASS-ASSIGNMENT` (Model `$guarded = []` protection)
     - `SEC-ENV-DEBUG-CSP` (APP_DEBUG suppression & CSP policy)
     - `SEC-SUPPLY-CHAIN` (composer.lock & package audit)
     - `SEC-CRYPTO-PII` (Dedicated PII encryption key isolation)
     - `SEC-BLADE-XSS` (Blade auto-escaping & `{!! !!}` protection)
     - `SEC-SOD-POLICY` (Segregation of Duties & BaseGovernmentPolicy)
     - `SEC-AUTH-THROTTLE` (Route rate limiting & session timeout)
     - `SEC-SERIALIZATION` (PHP `unserialize()` ban)
     - `SEC-AUDIT-LOGGING` (AuditActivityObserver registration)
     - `SEC-FAIL-CLOSED-SCOPE` (Fail-closed OfficeScope query evaluation)
   - Move the superseded edition (e.g., `'2025'`) to historical status (`'2025 (Historical Benchmark)'`).

### Step 2: Handle Novel Vulnerability Vectors (`src/Commands/LguAuditCommand.php`)
- **If OWASP reorders, renames, or merges categories:**
  - **Do NOT touch scanner logic.** The declarative mapping matrix handles all category translation automatically.
- **If OWASP introduces a completely new technical attack vector (e.g., LLM prompt injection, quantum crypto transition):**
  - Create a new invariant checker: `protected function checkInvariant{VectorName}(): array`
  - Define a new unique Rule ID (e.g., `SEC-AI-PROMPT-DEFENSE`).
  - Register it in `auditSecurityInvariants()`.

### Step 3: Review Reconciliations & Infrastructure Playbook
- In `SecurityStandardsMapper::getStatutoryReconciliations()`: ensure statutory directives (DPA RA 10173 vs. COA retention, Least Privilege vs. Oversight Access, Fail-Closed vs. ARTA) reference the updated OWASP category numbers.
- In `SecurityStandardsMapper::getInfrastructurePlaybook()`: update CloudPanel Nginx directives or Cloudflare Free Tier WAF settings if the new revision mandates additional edge protections.

### Step 4: Synchronize Documentation & Vendor Checklist
Keep all handbook and checklist copies synchronized:
1. `LARAVEL_LGU_DEVELOPMENT_STANDARDS.md` & `resources/docs/LARAVEL_LGU_DEVELOPMENT_STANDARDS.md`:
   - Update Section 2.6.2 table with the new OWASP categories, descriptions, and CloudPanel/Cloudflare actions.
2. `VENDOR_DELIVERY_CHECKLIST.md` & `resources/docs/VENDOR_DELIVERY_CHECKLIST.md`:
   - Update Section 5.1 checklist items for the new OWASP categories.
3. `README.md`:
   - Update framework version references, badges, and mermaid architecture diagrams.

### Step 5: Update Tests & Verify Quality Gates
1. In `tests/LguAuditCommandTest.php`:
   - Update expected output assertions to match the new `'<YEAR> (Active Version)'`.
   - Verify that `--owasp-version=<PREVIOUS_YEAR>` properly falls back to historical benchmark mode.
2. Run automated validation:
   - Check PHP syntax: `php -l {file}`
   - Code formatting: `./vendor/bin/pint --test`
   - Static analysis: `./vendor/bin/phpstan analyse`
   - Unit tests: `./vendor/bin/phpunit`

---

## 🧪 Verification Commands

```bash
# Lint specific file
php -l src/Security/SecurityStandardsMapper.php

# Run full test suite
./vendor/bin/phpunit

# Check styling
./vendor/bin/pint --test

# Run static type analysis
./vendor/bin/phpstan analyse

# Synchronize & assess NPC issuances
php artisan lgu:npc-sync --fetch-only

# Run statutory & security auditor (saves report to storage/app/lgu/audit-report.md)
php artisan lgu:audit --strict --output --fix-hint

# Run auditor against historical baseline
php artisan lgu:audit --owasp-version=2021
```
