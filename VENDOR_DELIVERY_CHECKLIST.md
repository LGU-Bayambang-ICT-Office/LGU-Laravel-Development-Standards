# LGU ICTO Vendor Delivery & Project Handover Acceptance Checklist

**Document Control Reference:** `ICTO-STD-CHK-01`  
**Issuing Authority:** Information and Communications Technology Office (ICTO), Municipality of Bayambang  
**Contact:** `icto@bayambang.gov.ph`  
**Applicability:** All internal ICTO software engineering projects and third-party vendor deliveries contracted by the Local Government Unit.

---

## 1. Project Identification

| Project Attribute | Details |
| :--- | :--- |
| **System / Application Name** | ____________________________________________________ |
| **Contract / Project Reference No.** | ____________________________________________________ |
| **Contracting Department / Office** | ____________________________________________________ |
| **Lead Developer / Vendor Name** | ____________________________________________________ |
| **Target Production Environment** | [ ] On-Premises LGU Data Center &nbsp;&nbsp; [ ] Cloud Infrastructure |
| **Date of Submission** | ________________________ |

---

## 2. Mandatory Quality & Automated Verification Gates

*All checks in this section must pass automated execution in the staging environment before acceptance review.*

| Requirement | Command / Verification Method | Pass Criteria | Status |
| :--- | :--- | :--- | :---: |
| **Automated Test Suite** | `php artisan test` or `./vendor/bin/pest` | **100% pass rate** on all unit and feature tests. Critical paths (auth, financial calculation, SLA computation, PII access) must have dedicated test coverage. | [ ] PASS<br>[ ] FAIL |
| **Code Style Compliance** | `./vendor/bin/pint --test` | **Zero violations** against LGU `pint.json` preset. | [ ] PASS<br>[ ] FAIL |
| **Static Code Analysis** | `./vendor/bin/phpstan analyse` | **Zero errors** at Larastan / PHPStan **Level 5** minimum. | [ ] PASS<br>[ ] FAIL |
| **Migration Reversibility** | `php artisan migrate:fresh --seed` | Clean execution without errors; deterministic seed data populates essential roles, permissions, and test accounts. | [ ] PASS<br>[ ] FAIL |
| **Asset Compilation** | `npm run build` | Clean production build with Vite; zero console or chunk-resolution errors. | [ ] PASS<br>[ ] FAIL |

---

## 3. MVC Architecture, Domain Logic & Database Audit

| Checkpoint | Standard Reference | Verification Details | Status |
| :--- | :--- | :--- | :---: |
| **Internal Primary Keys** | `BIGINT UNSIGNED id` | Every relational table uses auto-incrementing integer PK. No UUID primary keys on transactional/ledger tables. | [ ] PASS<br>[ ] FAIL |
| **Public Route Identifiers** | `HasRouteUuid` Trait | All public URLs and route-model bindings use immutable UUID v4 (`uuid` column). No sequential integer IDs exposed in routes. | [ ] PASS<br>[ ] FAIL |
| **Dedicated Show Routes** | `/{resource}/{uuid}` | Comprehensive record inspections (vouchers, records, permits) have dedicated URL routes. Zero monolithic modals for record review. | [ ] PASS<br>[ ] FAIL |
| **Livewire Modal Bounds** | Rule of 3 Fields | Modals strictly restricted to confirmations, state toggles, or $\le 3$ fields. Complex workflows graduate to dedicated routes. | [ ] PASS<br>[ ] FAIL |
| **Print & Binary Exports** | Controller Separation | Official printable layouts (`window.print()`) and binary exports (PDF/Excel) routed through dedicated Controllers, not Livewire actions. | [ ] PASS<br>[ ] FAIL |
| **Action Class Design** | Invokable `__invoke()` | Single-responsibility state mutations encapsulated in Action classes with `DB::transaction()` and pessimistic locking (`lockForUpdate()`). | [ ] PASS<br>[ ] FAIL |
| **Event `afterCommit` Guard** | `public bool $afterCommit` | All transactional domain events declare `$afterCommit = true` to prevent queued listeners running before DB commits. | [ ] PASS<br>[ ] FAIL |
| **Asynchronous Listeners** | `ShouldQueue` Interface | All side-effect listeners (SMS/Email notifications, PDF generation, webhook dispatches) implement `ShouldQueue`. | [ ] PASS<br>[ ] FAIL |
| **Configuration Architecture** | `.env` vs DB Settings | Permanent agency identity stored in `.env` and `config/lgu.php`; dynamic parameters (DPO, serials) hydrated from DB cache via `AppServiceProvider`. | [ ] PASS<br>[ ] FAIL |
| **CLI Settings Prompts** | `laravel/prompts` | Interactive Artisan commands provided for administrative setup without direct production `.env` editing. | [ ] PASS<br>[ ] FAIL |
| **Foreign Key Integrity** | `onDelete('restrict')` | Relational tables tied to financial, voucher, personnel, or audit histories strictly prohibit unmonitored cascade deletion. | [ ] PASS<br>[ ] FAIL |
| **Department / Office Scoping** | Fail-Closed `OfficeScope` | Multi-office transactional models fail closed (`1 = 0`) if active office session context is missing. | [ ] PASS<br>[ ] FAIL |
| **Naming Conventions Matrix** | Section 14 Matrix | Files, namespaces, and classes strictly adhere to the Universal Naming Conventions Matrix (singular models/controllers, typed actions, domain grouping when >5 files, zero junk classes). | [ ] PASS<br>[ ] FAIL |
| **Eloquent Policy Coverage** | Section 15.2 Mandate | Every citizen, financial, regulatory, and transactional Eloquent model has a dedicated registered Policy class implementing required domain abilities. | [ ] PASS<br>[ ] FAIL |
| **Policy-Mediated RBAC** | Section 15.4 Standard | Zero hardcoded `$user->hasRole()` or `@role()` checks in Blade, Livewire, or Controllers. All checks query atomic permissions via `$user->can()` / `@can()`. | [ ] PASS<br>[ ] FAIL |
| **Statutory SoD & Ceilings** | COA / RA 7160 Rules | Model policies strictly enforce Segregation of Duties (originator cannot approve) and monetary delegation ceilings. `Gate::before()` does not bypass financial approvals. | [ ] PASS<br>[ ] FAIL |

---

## 4. Philippine Statutory Compliance Audit

### 4.1 Data Privacy Act of 2012 (RA 10173) & NPC Circulars
- [ ] **Field-Level Encryption:** Sensitive Personal Information (SPI) such as government IDs (TIN, PhilHealth, SSS), personal contact numbers, and health records are encrypted at rest using `EncryptedPii` (AES-256-CBC) with an isolated encryption key.
- [ ] **Blind Index Implementation:** Searchable encrypted fields implement companion `_blind_index` columns using HMAC-SHA256 with application salt to prevent unindexed table scanning.
- [ ] **Right to Erasure Protocol:** Erasure requests execute an **anonymization / pseudonymization** pipeline for records linked to financial ledgers or administrative audits, preserving referential integrity while scrubbing personal identity.
- [ ] **Data Retention Schedules:** Automatic cleanup or archiving jobs configured for temporary logs and transient applicant submissions per NPC guidelines.

### 4.2 ARTA / Ease of Doing Business Act (RA 11032)
- [ ] **Standard SLA Classification:** Citizen service workflows explicitly classify processing windows into Simple (≤3 days), Complex (≤7 days), or Highly Technical (≤20 days).
- [ ] **Working Days Engine:** Turnaround time calculation utilizes `WorkingDaysService`, strictly excluding Saturdays, Sundays, and official national/local holidays.
- [ ] **Emergency Work Suspension Adjustment:** Dynamic SLA due date extension triggered automatically upon registration of typhoon or emergency office suspensions.
- [ ] **Client Satisfaction Measurement (CSM):** Integrated ARTA Harmonized CSM survey (ARTA MC No. 2023-05) upon service completion, covering SQD 0 (Overall Satisfaction) and the eight core dimensions SQD 1–8 (including SQD 7 Assurance and SQD 8 Outcome), with automated mean positive rating computation.

### 4.3 Commission on Audit (COA) Circulars & Financial Ledger Rules
- [ ] **Cryptographic Hash Chaining:** Audit log entries implement continuous row-level SHA-256 hash chaining ($H_n = SHA256(H_{n-1} || R_n)$).
- [ ] **Database Audit Freeze Trigger:** Database trigger (`trg_freeze_audit_ledger`) applied to prevent any `UPDATE` or `DELETE` on the `audit_activities` table.
- [ ] **Pessimistic Concurrency Locking:** High-concurrency financial operations (disbursements, cash receipts, inventory transfers) wrap in `DB::transaction` with `lockForUpdate()`.

### 4.4 National Archives of the Philippines (NAP) General Records Schedules
- [ ] **Record Series Classification:** Document management and routing records categorize documents under official NAP General Records Disposition Schedules.
- [ ] **Formal Disposal Requests:** Document destruction/disposal requires a structured approval workflow compliant with NAP disposal authorization requirements.

---

## 5. Security & Workstation Hardening Verification

- [ ] **Content Security Policy (CSP):** `spatie/laravel-csp` enabled with `LaravelViteNonceGenerator` injecting nonces into all script tags.
- [ ] **Session Idle Timeout:** `EnforceSessionTimeout` middleware enforces maximum 15–30 minute inactivity auto-logout on municipal office workstations.
- [ ] **Browser History Cache Prevention:** `PreventBackHistoryCache` middleware disables browser disk and memory caching on authenticated citizen/staff pages (`no-store, no-cache`).
- [ ] **Multi-Factor Authentication (MFA):** Two-factor authentication (TOTP) available and mandated for administrative and finance-handling accounts.
- [ ] **Rate Limiting:** Form submission endpoints, login routes, and public APIs implement strict Redis / cache-based rate limits (`throttle` middleware).

---

## 6. Deliverables, Source Code & IP Transfer

| Deliverable Item | Description | Verified |
| :--- | :--- | :---: |
| **Git Repository Transfer** | Clean Git repository transferred to official LGU Git organization with full commit history. | [ ] |
| **Dependency Lock Files** | Checked-in `composer.lock` and `package-lock.json` with zero outdated vulnerable packages (`composer audit`). | [ ] |
| **Environment Configuration** | Documented `.env.example` containing descriptive comments for every required variable without hardcoded production secrets. | [ ] |
| **Database Seeders** | Working `DatabaseSeeder` that provisions superadmin, role definitions, and realistic sandbox data. | [ ] |
| **Data Dictionary** | Comprehensive document detailing all database tables, columns, data types, indexes, and relationships. | [ ] |
| **Deployment Runbook** | Step-by-step installation, upgrade, backup restoration, and disaster recovery manual for LGU systems administrators. | [ ] |
| **Intellectual Property Waiver** | Signed IP assignment and copyright handover documents naming the Local Government Unit as owner. | [ ] |

---

## 7. Sign-off & Acceptance Review

| Role | Name | Signature | Date |
| :--- | :--- | :--- | :--- |
| **Vendor / Lead Software Engineer** | ___________________________ | ___________________________ | ____________ |
| **Data Protection Officer (DPO)** | ___________________________ | ___________________________ | ____________ |
| **ICTO Technical Lead / QA** | ___________________________ | ___________________________ | ____________ |
| **ICTO Department Head** | Head, ICTO | ___________________________ | ____________ |
