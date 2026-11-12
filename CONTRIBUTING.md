# Contributing to LGU Laravel Development Standards

Thank you for your interest in contributing to the **Philippine LGU Laravel Development Standards & GovSuite**!

This suite is maintained by the **Information and Communications Technology Office (ICTO), Municipality of Bayambang** to establish unified engineering, data privacy, and statutory compliance standards across Philippine local governments, software vendors, and municipal developers.

We welcome contributions from public sector developers, third-party contractors, and the open-source community that improve reliability, statutory alignment, and developer experience.

---

## 🔒 Security Vulnerabilities

> [!CAUTION]
> **Do not submit pull requests or public issues for suspected security vulnerabilities.**  
> Public disclosure exposes municipal information systems and citizen records to potential exploitation.

Please follow our coordinated disclosure policy in **[SECURITY.md](SECURITY.md)**:
* Submit a report via [GitHub Private Vulnerability Reporting](https://github.com/LGU-Bayambang-ICT-Office/LGU-Laravel-Development-Standards/security/advisories/new), or
* Email confidential disclosures directly to: `icto@bayambang.gov.ph`

---

## 📋 Submitting Issues

We use GitHub Issues to track bug reports, statutory enhancements, and developer tooling proposals.

### Ground Rules for Issues:
1. **Search Before Opening:** Search existing [open and closed issues](https://github.com/LGU-Bayambang-ICT-Office/LGU-Laravel-Development-Standards/issues) to confirm your issue or proposal has not already been addressed.
2. **Never Post Security Vulnerabilities:** If you suspect a security defect or access control bypass, do not open a public issue. Refer to **[SECURITY.md](SECURITY.md)** for private disclosure.
3. **Protect Citizen Privacy (RA 10173):** Never paste live municipal database dumps, real resident names, IDs, email addresses, passwords, or production `.env` keys into issue descriptions or logs. Always sanitize logs and use synthetic mock data.
4. **Use the Issue Templates:**
   * **🐛 Bug Report:** For unexpected behavior or errors. Please provide minimal reproduction steps, PHP/Laravel versions, and relevant stack traces.
   * **✨ Feature / Statutory Improvement Request:** For new traits, middleware, compliance checks, or architectural improvements supporting Philippine municipal requirements.

---

## 🧭 Contribution Principles

1. **Public Trust & Citizen Privacy First:**  
   Code must strictly uphold Republic Act No. 10173 (Data Privacy Act of 2012). Never commit real personal information (PII), live API credentials, municipal database dumps, or sensitive internal URLs in code, tests, or documentation.
2. **Statutory Alignment:**  
   Contributions touching statutory features (e.g., ARTA SLA calculations, COA audit trails, DPA encryption) must remain faithful to Philippine laws, official circulars, and civil service regulations.
3. **Fail-Closed Architecture:**  
   Security, authorization, and data isolation logic must always fail securely. If an office or permission cannot be determined, access must default to denied.
4. **Clean Code & Test Coverage:**  
   All contributions must pass automated tests and strict formatting checks before submission.

---

## 🛠️ Development Setup

### 1. Fork & Clone
Fork the repository on GitHub, then clone your fork locally:

```bash
git clone https://github.com/<your-username>/LGU-Laravel-Development-Standards.git
cd LGU-Laravel-Development-Standards
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Create a Topic Branch
Always branch off the `main` branch:

```bash
# For a new feature:
git checkout -b feat/arta-survey-builder

# For a bug fix:
git checkout -b fix/encrypted-pii-null-handling
```

---

## 🧪 Quality Gates & Verification

Before submitting your pull request, verify that all local checks pass cleanly:

### 1. Code Style (Laravel Pint)
All code must conform to the project's Pint rules defined in `pint.json`:

```bash
# Check formatting without modifying files
./vendor/bin/pint --test

# Automatically fix code formatting
./vendor/bin/pint
```

### 2. Automated Test Suite (PHPUnit)
All automated tests must pass with 100% green status. When introducing new classes, traits, or commands, you must include corresponding test cases under `tests/`:

```bash
./vendor/bin/phpunit tests
```

### 3. Static Type Safety
* All PHP files must start with `declare(strict_types=1);`.
* Specify explicit parameter types, return types, and class property types.
* Verify your changes against the project's `phpstan.neon`:
```bash
./vendor/bin/phpstan analyse
```

---

## 🏛️ Package Architecture Guidelines

* **Core Classes (`src/`):**  
  Classes under namespace `Lgu\GovSuite\...` are loaded directly when downstream applications install the Composer package. These classes must have minimal external dependencies and avoid hardcoded municipal assumptions.
* **Publishable Stubs (`stubs/`):**  
  Stubs are copied into the host application when `php artisan lgu:install` runs. If you modify a core trait or class in `src/`, check if an equivalent publishable stub exists in `stubs/` and keep them in sync.
* **Prompts & Console Commands:**  
  Console commands using interactive prompts (`Laravel\Prompts\...`) must include a fallback mechanism (e.g., `$this->choice()`) for non-interactive environments, CI/CD runners, and Windows terminals where POSIX TTY is unavailable.

---

## 🤖 AI Agent & Maintainer Guide: Updating for Future OWASP Top 10 Versions

When instructed to update this package for a new OWASP Top 10 revision (e.g., `OWASP Top 10:2028` or `OWASP Top 10:2029`), follow this systematic procedure:

### 1. Update the Mapping Matrix (`src/Security/SecurityStandardsMapper.php`)
- Update `DEFAULT_OWASP_VERSION` constant (e.g., `'2029'`).
- Update `ACTIVE_OWASP_LABEL` constant (e.g., `'OWASP Top 10:2029 (Active Version)'`).
- In `getFrameworks()`, add the new framework entry for the new version:
  - Map each new category (`A01:2029` .. `A10:2029`) to the underlying Technical Security Invariant Rule IDs (`SEC-MASS-ASSIGNMENT`, `SEC-ENV-DEBUG-CSP`, `SEC-SUPPLY-CHAIN`, `SEC-CRYPTO-PII`, `SEC-BLADE-XSS`, `SEC-SOD-POLICY`, `SEC-AUTH-THROTTLE`, `SEC-SERIALIZATION`, `SEC-AUDIT-LOGGING`, `SEC-FAIL-CLOSED-SCOPE`).
  - Move the previous edition (e.g., `2025`) to historical status (`'2025 (Historical Benchmark)'`).

### 2. Add New Invariants Only If Novel Vectors Emerge (`src/Commands/LguAuditCommand.php`)
- **Category Reordering / Merging:** Requires **zero** changes to `LguAuditCommand.php` scanner code. The declarative mapping matrix handles all category translation.
- **Novel Attack Vectors:** If the new OWASP revision introduces an unprecedented vulnerability class (e.g., LLM prompt injection or quantum crypto transition), implement a dedicated invariant method `checkInvariant{Vector}()` with a new unique Rule ID and register it in `auditSecurityInvariants()`.

### 3. Review Reconciliations & Infrastructure Playbook
- Verify `SecurityStandardsMapper::getStatutoryReconciliations()`: ensure statutory directives (DPA RA 10173 vs. COA retention, Least Privilege vs. Oversight Access, Fail-Closed vs. ARTA) accurately cite the new OWASP category numbers.
- Verify `SecurityStandardsMapper::getInfrastructurePlaybook()`: check if CloudPanel or Cloudflare configurations require additional security directives.

### 4. Synchronize Documentation & Checklists
1. `LARAVEL_LGU_DEVELOPMENT_STANDARDS.md` and `resources/docs/LARAVEL_LGU_DEVELOPMENT_STANDARDS.md`:
   - Update Section 2.6.2 table with the new OWASP categories, descriptions, code fixes, and CloudPanel/Cloudflare actions.
2. `VENDOR_DELIVERY_CHECKLIST.md` and `resources/docs/VENDOR_DELIVERY_CHECKLIST.md`:
   - Update Section 5.1 checklist items for the new OWASP categories.
3. `README.md`:
   - Update framework version labels and mermaid architecture diagrams.

### 5. Update Tests & Verify Quality Gates
- In `tests/LguAuditCommandTest.php`, update assertions to verify the new active version label and add the previous version to the fallback test.
- Run `php -l` on all PHP files.
- Run `./vendor/bin/pint --test` and `./vendor/bin/phpstan analyse`.
- Run PHPUnit tests to ensure 100% green status.

---

## 📝 Commit Conventions

We follow [Conventional Commits](https://www.conventionalcommits.org/):

| Prefix | Usage | Example |
| :--- | :--- | :--- |
| `feat:` | New feature or capability | `feat: add ARTA 8-dimension CSM survey helper` |
| `fix:` | Bug fix | `fix: prevent divide-by-zero in WorkingDaysService` |
| `docs:` | Documentation changes only | `docs: update vendor handover checklist instructions` |
| `test:` | Adding or improving tests | `test: add unit tests for LguInstallCommand prompts` |
| `refactor:` | Code change that neither fixes a bug nor adds a feature | `refactor: extract audit hash calculation into service` |
| `chore:` | Build tasks, configs, dependency bumps | `chore: bump laravel/prompts version constraint` |

---

## 🚀 Submitting Your Pull Request

1. **Push to Your Fork:**
   ```bash
   git push origin feat/your-feature-name
   ```
2. **Open the Pull Request:**
   * Open the PR against the `main` branch of `LGU-Bayambang-ICT-Office/LGU-Laravel-Development-Standards`.
   * Fill out the PR template completely:
     * Explain *why* the change is necessary and *what* it accomplishes.
     * Note any statutory or regulatory relevance (e.g., DPA, ARTA, COA).
     * Complete the pre-submission checklist.
3. **Continuous Integration & Review:**
   * Maintainers from the Information and Communications Technology Office (ICTO) will review your PR.
   * Feedback or revision requests will be communicated directly in the PR review thread.

---

## 📜 Contributor License Agreement (CLA)

By submitting a Pull Request, you agree that your contributions will be licensed under the project's **[Apache License 2.0](LICENSE)**.

You represent that:
1. You have the right to license the contributed code under Apache 2.0.
2. The code is your original work or you have clearly attributed any permissible open-source components.
3. The contribution contains no proprietary, classified, or copyrighted third-party material without appropriate authorization.

---

*Issued & Maintained by the Information and Communications Technology Office (ICTO), Municipality of Bayambang.*
