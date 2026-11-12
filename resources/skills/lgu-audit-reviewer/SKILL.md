---
name: lgu-audit-reviewer
description: >-
  Audits Laravel host applications against Philippine LGU Standards (LGU-ICTO-STD-2026-V1)
  and OWASP Top 10 invariants. Executes automated audits via MCP/artisan, performs semantic
  code reviews, and generates formal Markdown audit reports with vendor delivery gate status
  and ready-to-apply code diffs.
---

# LGU Laravel Compliance Audit & Code Review Skill (`lgu-audit-reviewer`)

This skill empowers the AI Agent to conduct statutory and security audits of a Laravel application against **LGU-ICTO-STD-2026-V1** and generate an executive-ready Markdown audit report complete with ready-to-apply code replacement diffs.

---

## 1. Triggering & Operating Modes

Execute the audit using the first available mode:

### Mode A: With Laravel Boost / MCP Active (Preferred)
Invoke the MCP tool to execute the deterministic compliance engine:
```json
{
  "tool": "execute_artisan",
  "arguments": {
    "command": "lgu:audit --format=json --strict --owasp-version=2025"
  }
}
```

### Mode B: Local Shell Execution (Progressive Fallback)
Run the Artisan audit command via shell execution:
```bash
php artisan lgu:audit --format=json --strict --owasp-version=2025
```

### Mode C: Pure Agent Static Code Inspection (Zero PHP Runtime)
If neither PHP nor MCP is available, the agent directly inspects the workspace:
1. `app/Models/*.php`: Scan for `HasRouteUuid`, `OfficeScope`, `$guarded = []`, `EncryptedPii` + `_blind_index`.
2. `database/migrations/*.php`: Scan for `$table->id()`, `$table->uuid('uuid')->unique()`, `restrictOnDelete()`.
3. `routes/web.php` & `routes/api.php`: Check for route parameter bindings and rate limiting.
4. `app/Http/Controllers/` & `app/Livewire/`: Verify MVC Show routes vs. Livewire modal input counts ($\le 3$).
5. `app/Policies/*.php`: Verify extension of `BaseGovernmentPolicy` and maker-checker checks.
6. `app/Actions/` & `app/Services/`: Check for raw SQL concatenations in queries.

---

## 2. Step-by-Step Audit Review Procedure

### Step 1: Run the Scanner & Parse JSON
Parse the JSON output from `lgu:audit`. Categorize the findings into:
- **Tier 1: Philippine Statutory Standards**
  - Code Quality Presets (`pint.json`, `phpstan.neon`)
  - Dual-Identifier Standard (`app/Models`)
  - PII Field Encryption & Blind Index (`EncryptedPii`, `HasPiiHashing`)
  - NPC Issuance Assessment Inventory (`storage/app/lgu/npc-assessment.json`)
  - Model Policy Coverage (`BaseGovernmentPolicy`)
  - Hardcoded Role Anti-Pattern (`hasRole('admin')`)
  - Event `afterCommit` Safety on Listeners
  - Printable Controller Separation (Dedicated PDF stream controllers)
  - Raw Database Query Sanitization (No SQL string interpolation)
- **Tier 2: OWASP Top 10 Invariants**
  - `SEC-MASS-ASSIGNMENT`, `SEC-ENV-DEBUG-CSP`, `SEC-SUPPLY-CHAIN`, `SEC-CRYPTO-PII`, `SEC-BLADE-XSS`, `SEC-SOD-POLICY`, `SEC-AUTH-THROTTLE`, `SEC-SERIALIZATION`, `SEC-AUDIT-LOGGING`, `SEC-FAIL-CLOSED-SCOPE`.

### Step 2: Semantic Code Deep-Dive
For every `FAIL` or `WARN` item:
1. Open and inspect the offending file using `read_file`.
2. Review the context around the violation (e.g. why is a raw SQL query being used? Is the Livewire component managing a 10-field form inside a modal?).
3. Verify if statutory mitigating controls exist.

### Step 3: Statutory Risk & Dual-Track Mitigation Evaluation
Evaluate against the delivery acceptance criteria in `VENDOR_DELIVERY_CHECKLIST.md`:
- **PASS:** 0 Failures, 0 Warnings in strict mode.
- **CONDITIONAL:** 0 Failures, but non-blocking warnings present (e.g., minor missing documentation).
- **FAIL:** Any statutory invariant failure (exposed integer IDs in routes, unencrypted SPI, missing maker-checker, destructive SQL on financial tables).

**Enforce Dual-Track Remediation:**
For every security invariant finding, distinguish between:
1. **Application Code Action:** What must be refactored in PHP / Laravel (e.g., Eloquent attributes, Form Requests, Policies).
2. **Infrastructure / Edge Action (Cloudflare & CloudPanel):** Actions that are better implemented or required as defense-in-depth at the web server (CloudPanel Nginx / PHP-FPM) or edge WAF (Cloudflare Free Tier), such as edge rate limiting, bot protection, TLS 1.3/HSTS, and HTTP response headers.

### Step 4: Generate the Markdown Audit Report
Write the audit report to `storage/app/lgu/audit-report-YYYY-MM-DD.md` (and present as a conversation artifact if requested).

---

## 3. Required Markdown Report Structure

The generated report MUST adhere to this exact standard template:

```markdown
# LGU Compliance Audit & Security Review Report
**Audit Date:** YYYY-MM-DD HH:MM:SS  
**Standard Reference:** LGU-ICTO-STD-2026-V1  
**Target Application:** {Host App Name / Vendor System}  
**Evaluator:** Antigravity AI Governance Auditor (Agent-Native Engine)  

---

## 1. Executive Scorecard

| Metric | Result | Target Benchmark | Status |
| :--- | :--- | :--- | :--- |
| **Statutory Standards (Tier 1)** | X / Y Passed (Z%) | 100% Mandatory | [ PASS / FAIL ] |
| **OWASP Top 10 Invariants (Tier 2)**| A / B Passed (C%) | 100% Mandatory | [ PASS / FAIL ] |
| **Vendor Acceptance Verdict** | **[ APPROVED / CONDITIONAL / REJECTED ]** | Approved Gate | [ STATUS ] |

---

## 2. Statutory Compliance Breakdown (Tier 1)

### 2.1 Data Privacy Act of 2012 (RA 10173) & NPC Issuances
- **SPI Encryption Assessment:** {Findings on EncryptedPii and _blind_index}
- **Applicable NPC Circulars:** {Status of npc-assessment.json}

### 2.2 COA Financial Immutability & Audit Trail
- **Audit Logging Observer:** {Status of AuditActivityObserver & trigger seals}
- **Destructive SQL Restrictions:** {Verification that DELETE/TRUNCATE are absent on ledgers}

### 2.3 ARTA / Ease of Doing Business (RA 11032)
- **SLA Timers & Event Decoupling:** {afterCommit safety on event listeners}

---

## 3. OWASP Top 10:2025 Security Invariants & Dual-Track Remediation (Tier 2)

| Rule ID | Category | Technical Check | Status | Primary Enforcement Track | Severity |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `SEC-MASS-ASSIGNMENT` | A01:2025 | Model Fillable / Guarded Protection | PASS / FAIL | Code (Model `$fillable`) | HIGH |
| `SEC-ENV-DEBUG-CSP` | A02:2025 | Production Debug & CSP Nonce | PASS / FAIL | Dual (Laravel CSP + CloudPanel Nginx) | CRITICAL |
| `SEC-SUPPLY-CHAIN` | A03:2025 | Composer Lock & Vulnerability Gate | PASS / FAIL | Code (composer audit) | HIGH |
| `SEC-CRYPTO-PII` | A04:2025 | Dedicated PII Key & SSL/TLS | PASS / FAIL | Dual (Laravel Cast + Cloudflare Full Strict) | CRITICAL |
| `SEC-BLADE-XSS` | A05:2025 | Blade Auto-Escaping Protection | PASS / FAIL | Code (Blade auto-escape) | MEDIUM |
| `SEC-SOD-POLICY` | A06:2025 | Segregation of Duties Enforcement | PASS / FAIL | Code (BaseGovernmentPolicy) | HIGH |
| `SEC-AUTH-THROTTLE` | A07:2025 | Rate Limiting & Session Timeouts | PASS / FAIL | Dual (Laravel Limiter + Cloudflare WAF) | HIGH |
| `SEC-SERIALIZATION` | A08:2025 | PHP Unserialize Ban | PASS / FAIL | Code (JSON-only storage) | HIGH |
| `SEC-AUDIT-LOGGING` | A09:2025 | Audit Activity Observer Active | PASS / FAIL | Dual (AuditObserver + DB SQL Trigger) | CRITICAL |
| `SEC-FAIL-CLOSED-SCOPE`| A10:2025 | Fail-Closed OfficeScope Tenancy | PASS / FAIL | Code (OfficeScope query filter) | HIGH |

---

## 4. Itemized Findings & Ready-to-Apply Diffs

### Finding #1: [Brief Description]
* **Severity:** CRITICAL / HIGH / MEDIUM
* **Statutory Citation:** Section X.X (Mandate reference)
* **File Location:** [`app/Models/Example.php:42`](file:///app/Models/Example.php#L42)
* **Finding Details:** {Explain root cause and violation}

#### Proposed Code Remediation Patch:
```diff
--- a/app/Models/Example.php
+++ b/app/Models/Example.php
@@ -12,3 +12,4 @@
 class Example extends Model
 {
-    protected $guarded = [];
+    use \Lgu\GovSuite\Traits\HasRouteUuid;
+    protected $fillable = ['uuid', 'office_id', 'title'];
 }
```

---

## 5. Infrastructure & Edge Hardening Blueprint (CloudPanel & Cloudflare Free Tier)

*Identify and prescribe infrastructure-level controls that protect against OWASP Top 10 vulnerabilities at the web server and network edge:*

### 5.1 CloudPanel Hosting Blueprint (Ubuntu 24.04 / Nginx / PHP 8.4)
- **Nginx Security Headers (A02 / A05):** In CloudPanel Site Settings > Vhost, inject:
  ```nginx
  add_header X-Frame-Options "SAMEORIGIN" always;
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "strict-origin-when-cross-origin" always;
  add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
  ```
- **PHP-FPM Hardening (A02 / A08):** Set `display_errors = Off`, `expose_php = Off`, and `allow_url_fopen = Off`. Verify the pool runs under an unprivileged, site-isolated user.
- **Sensitive Metadata & Dotfile Blocking (A01 / A02):** Verify Nginx blocks direct HTTP access:
  ```nginx
  location ~ /\.(env|git|htaccess) { deny all; return 404; }
  location ~ /(composer\.(json|lock)|package(-lock)?\.json) { deny all; return 404; }
  ```
- **Immutable Database Trigger Seal (A09 / COA):** Execute `database/sql/freeze_audit_logs_trigger.sql` directly on the database to prevent deletion or truncation even by DBA accounts.

### 5.2 Cloudflare Free Tier Blueprint (Edge Security, CDN & WAF)
- **SSL/TLS Strict Encryption (A02 / A04):** Set SSL/TLS mode to **"Full (Strict)"**. Enable **"Always Use HTTPS"** and **"Automatic HTTPS Rewrites"**. Set Minimum TLS Version to **TLS 1.2** or **TLS 1.3**.
- **HSTS Enforcement (A02):** Enable HSTS with `max-age=31536000` (1 year), `includeSubDomains`, and `preload`.
- **Bot Fight Mode & Browser Integrity (A07):** Turn ON **Bot Fight Mode** and **Browser Integrity Check** to block credential stuffing and automated fuzzers before reaching the origin server.
- **WAF Edge Rate Limiting (A07 - SEC-AUTH-THROTTLE):** Create Free Tier Rate Limiting Rules:
  - Match: URI Path equals `/login` or `/office/switch`
  - Rate: $\ge 5$ requests per 10 seconds per IP $\to$ Action: Block for 1 minute.

---

## 6. Prioritized Remediation Roadmap

1. **Immediate Blockers (Sprint 0 - Mandatory for Delivery Acceptance):**
   - Apply Diff #1 to resolve statutory non-compliance.
   - Configure Cloudflare SSL Full (Strict) and CloudPanel Nginx dotfile blocking.
2. **Security Invariants (Sprint 1):**
   - Address OWASP invariant warnings.
   - Activate Cloudflare WAF Rate Limiting on authentication endpoints.
3. **Architecture Polish (Sprint 2):**
   - Refactor Livewire modals exceeding the Rule of 3 into dedicated MVC Show routes.
```

---

## 4. Post-Audit Action Options

After presenting the report, the agent should offer to:
1. Automatically apply the proposed patches to the workspace files.
2. Re-run the audit to verify that the compliance score reaches 100%.
3. Generate a formal signed vendor acceptance markdown verification statement.
