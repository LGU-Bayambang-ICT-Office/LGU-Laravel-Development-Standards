---
name: lgu-standards-maintainer
description: >-
  Researches, maps, and synchronizes external regulatory updates for OWASP Top 10 and
  National Privacy Commission (NPC) issuances. Interactively confirms category-to-invariant
  mappings and updates SecurityStandardsMapper.php, JSON seed catalogs, and documentation.
---

# LGU Standards & Regulatory Maintenance Skill (`lgu-standards-maintainer`)

This skill equips the AI Agent to keep the LGU Laravel Standards package up to date with external regulatory shifts, specifically future **OWASP Top 10 revisions** and new **National Privacy Commission (NPC) Data Privacy Issuances**, without requiring manual code refactoring or external artisan commands.

---

## 1. Triggering & Maintenance Roles

Invoke this skill whenever:
- A new OWASP Top 10 version is published (e.g., OWASP Top 10:2028 or 2029).
- The National Privacy Commission (NPC) issues new circulars, advisories, or compliance guidelines under the Data Privacy Act (RA 10173).
- An annual compliance audit benchmark update is scheduled.

---

## 2. Workflow 1: OWASP Top 10 Framework Update

When updating the package for a new OWASP Top 10 release:

### Step 1: Research Official Framework Details
Use web search or official documentation to gather:
- Active release year (e.g., `2029`).
- Category codes and naming (`A01:2029` through `A10:2029`).
- Underlying vulnerability descriptions and attack vectors.

### Step 2: Map Categories to Core Invariant Rule IDs
Map each new category to the decoupled invariant rules in `SecurityStandardsMapper.php`:
1. `SEC-MASS-ASSIGNMENT`: Model mass-assignment & route parameter tampering.
2. `SEC-ENV-DEBUG-CSP`: Production debug suppression & Content Security Policy nonces.
3. `SEC-SUPPLY-CHAIN`: Dependency lockfile integrity & security vulnerability audit.
4. `SEC-CRYPTO-PII`: Dedicated PII encryption key isolation & AES-256-CBC.
5. `SEC-BLADE-XSS`: Blade auto-escaping & unescaped `{!! !!}` protection.
6. `SEC-SOD-POLICY`: Segregation of Duties & maker-checker enforcement.
7. `SEC-AUTH-THROTTLE`: Route rate limiting & session workstation timeouts.
8. `SEC-SERIALIZATION`: PHP `unserialize()` ban.
9. `SEC-AUDIT-LOGGING`: `AuditActivityObserver` registration & cryptographic chaining.
10. `SEC-FAIL-CLOSED-SCOPE`: Fail-closed `OfficeScope` tenancy query evaluation.

> [!NOTE]
> **Novel Attack Vectors:** If OWASP introduces a completely new attack vector (e.g., AI LLM prompt injection, quantum cryptography transition), define a new unique Rule ID (e.g., `SEC-AI-PROMPT-DEFENSE`) and add an invariant checker method to `LguAuditCommand.php`.

### Step 3: Present Proposed Mapping for Confirmation
Present the proposed mapping to the developer in chat:
```markdown
### Proposed OWASP Top 10:<YEAR> Mapping Matrix
| Category | Official Name | Mapped Invariant Rule ID | Technical Check |
| :--- | :--- | :--- | :--- |
| A01:<YEAR> | ... | SEC-MASS-ASSIGNMENT | Mass Assignment Protection |
...
```

### Step 4: Execute Multi-File Synchronization
Upon confirmation, update the following files:
1. **`src/Security/SecurityStandardsMapper.php`:**
   - Update `public const DEFAULT_OWASP_VERSION = '<YEAR>';`
   - Update `public const ACTIVE_OWASP_LABEL = 'OWASP Top 10:<YEAR> (Active Version)';`
   - Add the new framework entry to `getFrameworks()` and move the previous active version to `'(Historical Benchmark)'`.
2. **`LARAVEL_LGU_DEVELOPMENT_STANDARDS.md` & `resources/docs/`:**
   - Update Section 2.6.2 table with the new categories, descriptions, and CloudPanel/Cloudflare actions.
3. **`VENDOR_DELIVERY_CHECKLIST.md` & `resources/docs/`:**
   - Update Section 5.1 checklist items.
4. **`tests/LguAuditCommandTest.php`:**
   - Update test assertions for default active version and historical benchmark fallback.

### Step 5: Test & Verify
Run automated verification:
```bash
# Check syntax
php -l src/Security/SecurityStandardsMapper.php

# Run PHPUnit test suite
./vendor/bin/phpunit tests/LguAuditCommandTest.php
```

---

## 3. Workflow 2: NPC Data Privacy Issuances Update

When new NPC issuances are published:

### Step 1: Research NPC Advisories & Circulars
Search `https://privacy.gov.ph/advisories-circulars/` or user-provided circular references.

### Step 2: Extract Metadata
For each new issuance, extract:
- **`id`**: Canonical key (e.g., `NPC-CIRC-2026-01`)
- **`type`**: `Circular`, `Advisory`, or `Advisory Opinion`
- **`number`**: Issuance number (e.g., `2026-01`)
- **`year`**: Year of issuance (e.g., `2026`)
- **`title`**: Full statutory title
- **`issued_at`**: ISO date (`YYYY-MM-DD`)
- **`status`**: `Active`, `Amended`, or `Superseded`
- **`lgu_applicability`**: `Mandatory`, `Conditional`, or `Informational`
- **`relevance_summary`**: 1-2 sentence municipal IT impact summary
- **`action_required`**: Concrete engineering or policy requirement for LGU systems

### Step 3: Append to Seed Catalog
Add the structured issuance to `resources/data/npc-catalog-seed.json`:
```json
{
  "id": "NPC-CIRC-2026-01",
  "type": "Circular",
  "number": "2026-01",
  "year": 2026,
  "title": "Guidelines on Automated Decision-Making and AI Systems Processing Personal Data in Government",
  "issued_at": "2026-03-15",
  "status": "Active",
  "lgu_applicability": "Mandatory",
  "relevance_summary": "Regulates automated scoring, eligibility checking, and AI processing of citizen data in local government systems.",
  "action_required": "Ensure audit logs record AI model decision confidence scores and maintain human-in-the-loop review mechanisms."
}
```

### Step 4: Synchronize Local Assessment Inventory
Update the local development assessment inventory (`storage/app/lgu/npc-assessment.json`) so subsequent audits evaluate compliance against the new issuance.

### Step 5: Verify Catalog Integrity
Run test:
```bash
./vendor/bin/phpunit tests/LguNpcSyncCommandTest.php
```
