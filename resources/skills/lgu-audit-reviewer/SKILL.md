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

### Step 3: Statutory Risk & Vendor Gate Evaluation
Evaluate against the delivery acceptance criteria in `VENDOR_DELIVERY_CHECKLIST.md`:
- **PASS:** 0 Failures, 0 Warnings in strict mode.
- **CONDITIONAL:** 0 Failures, but non-blocking warnings present (e.g., minor missing documentation).
- **FAIL:** Any statutory invariant failure (exposed integer IDs in routes, unencrypted SPI, missing maker-checker, destructive SQL on financial tables).

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

## 3. OWASP Top 10:2025 Security Invariants (Tier 2)

| Rule ID | Category | Technical Check | Status | Severity |
| :--- | :--- | :--- | :--- | :--- |
| `SEC-MASS-ASSIGNMENT` | A01:2025 | Model Fillable / Guarded Protection | PASS / FAIL | HIGH |
| `SEC-ENV-DEBUG-CSP` | A02:2025 | Production Debug & CSP Nonce | PASS / FAIL | CRITICAL |
| `SEC-SUPPLY-CHAIN` | A03:2025 | Composer Lock & Vulnerability Gate | PASS / FAIL | HIGH |
| `SEC-CRYPTO-PII` | A04:2025 | Dedicated PII Key Isolation | PASS / FAIL | CRITICAL |
| `SEC-BLADE-XSS` | A05:2025 | Blade Auto-Escaping Protection | PASS / FAIL | MEDIUM |
| `SEC-SOD-POLICY` | A06:2025 | Segregation of Duties Enforcement | PASS / FAIL | HIGH |
| `SEC-AUTH-THROTTLE` | A07:2025 | Rate Limiting & Session Timeouts | PASS / FAIL | HIGH |
| `SEC-SERIALIZATION` | A08:2025 | PHP Unserialize Ban | PASS / FAIL | HIGH |
| `SEC-AUDIT-LOGGING` | A09:2025 | Audit Activity Observer Active | PASS / FAIL | CRITICAL |
| `SEC-FAIL-CLOSED-SCOPE`| A10:2025 | Fail-Closed OfficeScope Tenancy | PASS / FAIL | HIGH |

---

## 4. Itemized Findings & Ready-to-Apply Diffs

### Finding #1: [Brief Description]
* **Severity:** CRITICAL / HIGH / MEDIUM
* **Statutory Citation:** Section X.X (Mandate reference)
* **File Location:** [`app/Models/Example.php:42`](file:///app/Models/Example.php#L42)
* **Finding Details:** {Explain root cause and violation}

#### Proposed Remediation Patch:
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

## 5. Prioritized Remediation Roadmap

1. **Immediate Blockers (Sprint 0 - Mandatory for Delivery Acceptance):**
   - Apply Diff #1 to resolve statutory non-compliance.
2. **Security Invariants (Sprint 1):**
   - Address OWASP invariant warnings.
3. **Architecture Polish (Sprint 2):**
   - Refactor Livewire modals exceeding the Rule of 3 into dedicated MVC Show routes.
```

---

## 4. Post-Audit Action Options

After presenting the report, the agent should offer to:
1. Automatically apply the proposed patches to the workspace files.
2. Re-run the audit to verify that the compliance score reaches 100%.
3. Generate a formal signed vendor acceptance markdown verification statement.
