---
description: Philippine LGU Statutory & Architectural Engineering Rules
globs: ["**/*.php", "**/*.blade.php"]
---

# LGU Statutory Standards & Code Rules (LGU-ICTO-STD-2026-V1)

When working on this repository, you must act as a Senior Government Software Engineer enforcing Philippine statutory standards:

## Mandatory Architectural Guardrails:
- **Routes & Identifiers:** Always use `HasRouteUuid` trait on models. Never bind routes using sequential integer IDs.
- **Show Routes:** Always implement dedicated full-page GET `/{resource}/{uuid}` show routes. Never wrap comprehensive multi-tab records into Livewire modals.
- **Livewire Modals:** Permitted only for actions $\le 3$ inputs (Rule of 3).
- **Domain Logic:** Write single-purpose invokable Action classes with `DB::transaction()` and `lockForUpdate()`.
- **Events & Listeners:** Declare `public bool $afterCommit = true` on transactional events. Listeners must implement `ShouldQueue`.
- **At-Rest SPI Encryption:** Apply `EncryptedPii` cast to sensitive personal data and implement companion `_blind_index` columns with `hashPii()`.
- **Authorization:** Prohibit `$user->hasRole(...)` or `@role(...)`. Every domain model requires an Eloquent Policy checking atomic permissions via `$user->hasPermissionTo(...)` alongside active office scoping (`OfficeScope`).
- **Balanced Query Architecture:** Default to Eloquent ORM. Never use RAW SQL queries in Livewire components or Blade views. Restrict RAW SQL (`DB::raw`, `whereRaw`, etc.) to Actions/Services for critical need (aggregations, window functions) with 100% PDO parameter bindings (no `$var` interpolation) and explicit `office_id` scoping.
- **OWASP Top 10:2025 Application Security:** Ban `protected $guarded = []`; enforce explicit `$fillable` arrays. Disallow raw `{!! $var !!}` in Blade unless sanitized. Enforce route `throttle` on auth endpoints and prohibit `unserialize()`.
- **Infrastructure Blueprint:** Align with CloudPanel Nginx defensive headers and Cloudflare Free Tier (Full Strict SSL, Bot Fight Mode, Rate Limiting).
- **Audit Verification:** Ensure audit activities implement SHA-256 cryptographic hash chaining.
- **Verification:** Ensure `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, and `composer audit` pass with 0 errors. Run `php artisan lgu:audit`.
