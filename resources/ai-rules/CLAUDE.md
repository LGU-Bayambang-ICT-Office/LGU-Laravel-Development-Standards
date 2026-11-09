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

7. **Quality & Audit Checks:**
   - Format with `./vendor/bin/pint --test`
   - Analyse with `./vendor/bin/phpstan analyse`
   - Run statutory compliance check: `php artisan lgu:audit`
