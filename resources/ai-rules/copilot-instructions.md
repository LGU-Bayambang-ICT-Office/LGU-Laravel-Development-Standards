# GitHub Copilot Instructions for Philippine LGU Laravel Applications

## Architectural Guidelines:
1. **Primary Keys vs Route Keys:** Always use `BIGINT UNSIGNED id` for internal foreign keys. Always use `HasRouteUuid` for route model binding. Never expose database integer IDs in URLs.
2. **Show Routes:** Always use full-page dedicated show routes `/{resource}/{uuid}` for detailed record inspections. Never create monolithic Livewire modals for reviewing records.
3. **Modal Limits:** Restrict Livewire modals to confirmations or $\le 3$ input fields.
4. **Data Privacy (RA 10173):** Encrypt sensitive personal information at rest using `EncryptedPii` cast. Use companion `_blind_index` columns for searching.
5. **Business Logic:** Encapsulate state mutations into invokable Action classes with `DB::transaction()` and pessimistic locking.
6. **Events:** Always set `public bool $afterCommit = true` on transactional events. Listeners must implement `ShouldQueue`.
7. **Authorization:** Never use `$user->hasRole()` or `@role()` in controllers or views. Use native Policies and check atomic permissions with `$user->can()` / `@can()`.
8. **Balanced Query Architecture:** Default to Eloquent ORM. Never place raw SQL in Livewire components or Blade views. Restrict RAW SQL (`DB::raw`, `whereRaw`, etc.) to Actions/Services for critical need (aggregations, window functions) with 100% PDO parameter bindings and explicit `office_id` scoping.
9. **Compliance Checks:** Always test code using `php artisan lgu:audit` and ensure `./vendor/bin/pint --test` passes.
