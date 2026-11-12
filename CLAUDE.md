# Claude Code Guidance: Philippine LGU Laravel Development Standards

This codebase complies with **LGU-ICTO-STD-2026-V1** for Philippine municipal government software.

## Core Directives for Claude:

1. **Dual-Identifier Standard:**
   - Every domain model MUST have `BIGINT UNSIGNED id` primary key.
   - Every public route MUST use `uuid` via `HasRouteUuid` trait. Do not expose integer IDs.

2. **MVC vs. Livewire Separation:**
   - Detailed record reviews MUST have dedicated `/{resource}/{uuid}` show routes.
   - Livewire modals are strictly for confirmations or forms with $\le 3$ inputs.
   - Printable forms and PDF streaming belong in dedicated single-action Controllers.

3. **Data Privacy (RA 10173):**
   - SPI fields (TIN, PhilHealth, mobile, etc.) must use `EncryptedPii` cast.
   - Companion `_blind_index` columns with `hashPii()` must be used for exact-match SQL search.

4. **Domain Logic:**
   - Business mutations: Invokable Action classes (`app/Actions/{Domain}/`) with `DB::transaction()` and `lockForUpdate()`.
   - Domain events: Always declare `public bool $afterCommit = true`.
   - Listeners: Must implement `ShouldQueue`.

5. **Authorization:**
   - Never write `@role(...)` or `$user->hasRole(...)`.
   - Route all authorization through Eloquent Policies using `@can(...)` or `$this->authorize(...)`.
   - Ensure policies check active office scope (`active_office_id`), monetary limits, and Segregation of Duties.

6. **Balanced Query Architecture & RAW SQL Limitations:**
   - Default to Eloquent ORM. Never place raw SQL in Livewire components or Blade views.
   - Restrict RAW SQL (`DB::raw`, `whereRaw`, etc.) to Actions/Services for critical need (aggregations, window functions, locks).
   - Mandatory PDO parameter binding (never interpolate variables with `$`). Always preserve `office_id` scoping.

7. **OWASP Top 10:2025 Application Security:**
   - Prohibit `$guarded = []`. Always define explicit `$fillable` arrays on domain models.
   - Default to `{{ $var }}` in Blade. `{!! $var !!}` is strictly forbidden unless sanitized via HTMLPurifier.
   - Enforce `throttle` on authentication routes; prohibit `unserialize()`.
   - Align hosting with CloudPanel Nginx security headers and Cloudflare Free Tier (Full Strict SSL, Bot Fight Mode, Rate Limiting).

8. **Quality & Audit Checks:**
   - Format with `./vendor/bin/pint --test`
   - Analyse with `./vendor/bin/phpstan analyse`
   - Run dependency check: `composer audit`
   - Run statutory & OWASP compliance check: `php artisan lgu:audit`

9. **Updating for Future OWASP Top 10 Versions (AI Agent Runbook):**
   - Consult `AGENTS.md` and `CONTRIBUTING.md` for the 5-step maintenance runbook.
   - Update `src/Security/SecurityStandardsMapper.php` constants (`DEFAULT_OWASP_VERSION`, `ACTIVE_OWASP_LABEL`) and add the new category mapping in `getFrameworks()`.
   - Invariant scanner code in `LguAuditCommand.php` remains unchanged unless a novel attack vector requires a new `SEC-*` rule.
   - Synchronize Section 2.6 in `LARAVEL_LGU_DEVELOPMENT_STANDARDS.md` and Section 5.1 in `VENDOR_DELIVERY_CHECKLIST.md`.
