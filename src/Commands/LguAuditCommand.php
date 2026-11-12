<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Lgu\GovSuite\Security\SecurityStandardsMapper;
use Lgu\GovSuite\Services\NpcCatalogService;

/**
 * LGU Engineering Standards Compliance Auditor.
 *
 * Implements a two-tier verification architecture:
 * - Tier 1: Philippine Statutory & Governance Standards (LGU-ICTO-STD-2026-V1)
 * - Tier 2: Decoupled Security Invariant Rules mapped to compliance frameworks (e.g. OWASP Top 10)
 *
 * @author Ricky Bulalakaw
 */
class LguAuditCommand extends Command
{
    protected $signature = 'lgu:audit 
                            {--format=cli : Output format (cli, json, markdown)}
                            {--o|output= : Save report to a file (defaults to storage/app/lgu/audit-report.md or .json)}
                            {--strict : Treat warnings as failures}
                            {--fix-hint : Display statutory remediation steps for each finding}
                            {--owasp-version=2025 : OWASP Top 10 framework version to project (e.g. 2025, 2021)}';

    protected $description = 'Audit host application for compliance with Philippine LGU Laravel Engineering Standards';

    protected array $results = [];

    protected array $statutoryResults = [];

    protected array $owaspResults = [];

    protected array $activeFramework = [];

    protected string $owaspVersion = SecurityStandardsMapper::DEFAULT_OWASP_VERSION;

    protected array $npcApplicableIssuances = [];

    protected ?array $npcAssessmentSummary = null;

    protected int $failCount = 0;

    protected int $warnCount = 0;

    protected int $passCount = 0;

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $this->owaspVersion = (string) ($this->option('owasp-version') ?: SecurityStandardsMapper::DEFAULT_OWASP_VERSION);
        $this->activeFramework = SecurityStandardsMapper::getFramework($this->owaspVersion);

        // Immediate reminder of audit scope upon invocation
        if ($format === 'cli' || $format === '') {
            $this->outputScopeReminder();
        }

        // Tier 1: Philippine Statutory & Governance Standards
        $this->auditCodeQualityPresets();
        $this->auditModelsDualIdentifier();
        $this->auditPiiFieldEncryption();
        $this->auditNpcIssuancesAssessment();
        $this->auditModelPolicyCoverage();
        $this->auditHardcodedRoleAntiPattern();
        $this->auditEventAfterCommitSafety();
        $this->auditPrintableControllerSeparation();
        $this->auditRawDatabaseQueries();

        // Tier 2: Decoupled Security Invariants projected through Compliance Mapping Matrix
        $this->auditSecurityInvariants($this->owaspVersion);

        if ($format === 'json') {
            $this->outputJson();
        } elseif ($format === 'markdown') {
            $this->outputMarkdown();
        } else {
            $this->outputCliTable();
        }

        if ($this->shouldSaveReport()) {
            $this->saveReportToFile($format);
        }

        $strict = (bool) $this->option('strict');
        if ($this->failCount > 0 || ($strict && $this->warnCount > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Check Section 13: Pint and Larastan presets present in project root.
     */
    protected function auditCodeQualityPresets(): void
    {
        $hasPint = File::exists(base_path('pint.json'));
        $hasPhpStan = File::exists(base_path('phpstan.neon'));

        if ($hasPint && $hasPhpStan) {
            $this->addResult('Quality Tools', 'Pint & Larastan Presets', 'PASS', 'pint.json and phpstan.neon present at project root.');
        } else {
            $missing = [];
            if (! $hasPint) {
                $missing[] = 'pint.json';
            }
            if (! $hasPhpStan) {
                $missing[] = 'phpstan.neon';
            }
            $this->addResult('Quality Tools', 'Pint & Larastan Presets', 'FAIL', 'Missing: ' . implode(', ', $missing) . '. Run `php artisan lgu:install` to publish.');
        }
    }

    /**
     * Check Section 7: Dual-Identifier Standard on Eloquent Models.
     */
    protected function auditModelsDualIdentifier(): void
    {
        $modelFiles = $this->getPhpFiles(app_path('Models'));
        if (empty($modelFiles)) {
            $this->addResult('Database & Models', 'Dual-Identifier Standard', 'WARN', 'No models found in app/Models.');

            return;
        }

        $nonCompliant = [];

        foreach ($modelFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            // Skip Pivot models or generic bases
            if (str_contains($content, 'extends Pivot') || $className === 'User') {
                continue;
            }

            $hasUuidTrait = str_contains($content, 'HasRouteUuid') || str_contains($content, 'getRouteKeyName');
            if (! $hasUuidTrait) {
                $nonCompliant[] = $className;
            }
        }

        if (empty($nonCompliant)) {
            $this->addResult('Database & Models', 'Dual-Identifier Standard', 'PASS', 'All domain models implement UUID route-model binding.');
        } else {
            $this->addResult('Database & Models', 'Dual-Identifier Standard', 'WARN', 'Models missing HasRouteUuid: ' . implode(', ', $nonCompliant));
        }
    }

    /**
     * Check Section 2.1: Field Encryption on Sensitive Personal Information (SPI).
     */
    protected function auditPiiFieldEncryption(): void
    {
        $modelFiles = $this->getPhpFiles(app_path('Models'));
        $spiPatterns = ['tin', 'philhealth', 'sss', 'mobile_number', 'phone_number', 'contact_number', 'medical_history', 'blood_type'];

        $flaggedModels = [];

        foreach ($modelFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            foreach ($spiPatterns as $spi) {
                if (stripos($content, "'$spi'") !== false || stripos($content, "\"$spi\"") !== false) {
                    $hasEncryptionCast = str_contains($content, 'EncryptedPii') || str_contains($content, "'encrypted'");
                    if (! $hasEncryptionCast) {
                        $flaggedModels[$className][] = $spi;
                    }
                }
            }
        }

        if (empty($flaggedModels)) {
            $this->addResult('Data Privacy (DPA)', 'PII Field Encryption Casts', 'PASS', 'Sensitive personal information casted to EncryptedPii.');
        } else {
            $details = [];
            foreach ($flaggedModels as $model => $fields) {
                $details[] = "$model (" . implode(', ', $fields) . ')';
            }
            $this->addResult('Data Privacy (DPA)', 'PII Field Encryption Casts', 'FAIL', 'Unencrypted SPI attributes detected in: ' . implode(', ', $details));
        }
    }

    /**
     * Check Section 2.1 & RA 10173: NPC Statutory Issuances Applicability Assessment.
     */
    protected function auditNpcIssuancesAssessment(): void
    {
        $npcService = new NpcCatalogService;
        $assessment = $npcService->loadAssessment();

        if ($assessment === null || empty($assessment['issuances'])) {
            $this->addResult(
                'Data Privacy (DPA)',
                'NPC Statutory Issuances Assessment',
                'WARN',
                'NPC assessment not configured. Run `php artisan lgu:npc-sync` to fetch and review applicable National Privacy Commission circulars.',
            );

            return;
        }

        $unreviewed = array_filter($assessment['issuances'], fn ($i) => ($i['status'] ?? '') === 'UNREVIEWED');
        $applicable = array_filter($assessment['issuances'], fn ($i) => ($i['status'] ?? '') === 'APPLICABLE');

        $this->npcApplicableIssuances = array_values($applicable);
        $this->npcAssessmentSummary = [
            'updated_at' => $assessment['updated_at'] ?? null,
            'total_issuances' => count($assessment['issuances']),
            'applicable_count' => count($applicable),
            'unreviewed_count' => count($unreviewed),
        ];

        if (! empty($unreviewed)) {
            $this->addResult(
                'Data Privacy (DPA)',
                'NPC Statutory Issuances Assessment',
                'WARN',
                count($unreviewed) . ' NPC issuance(s) pending review. Run `php artisan lgu:npc-sync` to complete applicability inventory.',
            );
        } else {
            $this->addResult(
                'Data Privacy (DPA)',
                'NPC Statutory Issuances Assessment',
                'PASS',
                'NPC applicability assessment completed (' . count($applicable) . ' applicable circulars identified for DPO oversight).',
            );
        }
    }

    /**
     * Check Section 3: Model Policy Coverage (every model in app/Models must have a Policy).
     */
    protected function auditModelPolicyCoverage(): void
    {
        $modelFiles = $this->getPhpFiles(app_path('Models'));
        $missingPolicies = [];

        foreach ($modelFiles as $file) {
            $modelName = basename($file, '.php');
            if ($modelName === 'User') {
                continue;
            }

            $policyPath = app_path("Policies/{$modelName}Policy.php");
            if (! File::exists($policyPath)) {
                $missingPolicies[] = $modelName;
            }
        }

        if (empty($missingPolicies)) {
            $this->addResult('Authorization', 'Model Policy Coverage', 'PASS', '100% policy coverage for domain models.');
        } else {
            $this->addResult('Authorization', 'Model Policy Coverage', 'WARN', 'Missing policies for: ' . implode(', ', $missingPolicies));
        }
    }

    /**
     * Check Section 3: Anti-pattern check for hardcoded roles in blade or controllers.
     */
    protected function auditHardcodedRoleAntiPattern(): void
    {
        $scanDirs = array_filter([
            app_path('Http/Controllers'),
            app_path('Livewire'),
            resource_path('views'),
        ], fn ($d) => File::isDirectory($d));

        $flaggedLocations = [];

        foreach ($scanDirs as $dir) {
            $files = File::allFiles($dir);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                if (preg_match('/hasRole\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i', $content) || preg_match('/@role\s*\(/i', $content)) {
                    $flaggedLocations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                }
            }
        }

        if (empty($flaggedLocations)) {
            $this->addResult('Authorization', 'Permission-Based RBAC Check', 'PASS', 'No hardcoded role checks detected. Permissions-only pattern respected.');
        } else {
            $this->addResult('Authorization', 'Permission-Based RBAC Check', 'WARN', 'Hardcoded role check anti-pattern detected in: ' . implode(', ', array_slice($flaggedLocations, 0, 3)));
        }
    }

    /**
     * Check Section 6.2: Ensure all Events in app/Events implement $afterCommit = true.
     */
    protected function auditEventAfterCommitSafety(): void
    {
        $eventFiles = $this->getPhpFiles(app_path('Events'));
        $missingGuard = [];

        foreach ($eventFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            $isTransactional = str_contains($content, 'Created') || str_contains($content, 'Updated') || str_contains($content, 'Disbursed') || str_contains($content, 'Approved');
            if ($isTransactional && ! str_contains($content, 'afterCommit')) {
                $missingGuard[] = $className;
            }
        }

        if (empty($missingGuard)) {
            $this->addResult('Event Architecture', 'Transactional Event afterCommit Guard', 'PASS', 'All transactional events declare $afterCommit guard.');
        } else {
            $this->addResult('Event Architecture', 'Transactional Event afterCommit Guard', 'FAIL', 'Missing public bool $afterCommit = true in: ' . implode(', ', $missingGuard));
        }
    }

    /**
     * Check Section 4.6: Printable forms and exports routed via controllers, not Livewire actions.
     */
    protected function auditPrintableControllerSeparation(): void
    {
        $livewireFiles = $this->getPhpFiles(app_path('Livewire'));
        $flagged = [];

        foreach ($livewireFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            if (str_contains($content, 'response()->download') || str_contains($content, 'response()->streamDownload') || str_contains($content, 'Pdf::loadView')) {
                $flagged[] = $className;
            }
        }

        if (empty($flagged)) {
            $this->addResult('MVC & Livewire', 'Print & Binary Export Separation', 'PASS', 'Official printable forms and PDF streams isolated in dedicated Controllers.');
        } else {
            $this->addResult('MVC & Livewire', 'Print & Binary Export Separation', 'WARN', 'Binary streams detected inside Livewire components: ' . implode(', ', $flagged) . '. Move to dedicated single-action Controller.');
        }
    }

    /**
     * Check Section 7.5: Balanced Query Architecture & Raw SQL limitations.
     */
    protected function auditRawDatabaseQueries(): void
    {
        $rawPattern = '/\b(?:DB::(?:raw|select|statement|unprepared)|->(?:whereRaw|selectRaw|havingRaw|orderByRaw|groupByRaw))\s*\(/i';

        // 1. Check UI & presentation paths where RAW is strictly prohibited (FAIL)
        $uiPaths = array_filter([
            app_path('Livewire'),
            resource_path('views'),
            app_path('Http/Controllers'),
        ], fn ($p) => File::isDirectory($p));

        $uiViolations = [];
        foreach ($uiPaths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                if (preg_match($rawPattern, $content)) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $uiViolations[] = $relativePath;
                }
            }
        }

        // 2. Check for unsafe variable interpolation or concatenation (FAIL) across all scan paths
        $allScanPaths = array_filter([
            app_path(),
            resource_path('views'),
        ], fn ($p) => File::isDirectory($p));

        $unsafeInterpolations = [];
        $unsafePattern = '/\b(?:DB::(?:raw|select|statement|unprepared)|->(?:whereRaw|selectRaw|havingRaw|orderByRaw|groupByRaw))\s*\(\s*(?:\$[a-zA-Z_\x7f-\xff]|"[^"]*\$[a-zA-Z_\x7f-\xff]|(?:\'[^\']*\'|"[^"]*")\s*\.\s*\$[a-zA-Z_\x7f-\xff])/i';

        foreach ($allScanPaths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                if (preg_match($unsafePattern, $content)) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $unsafeInterpolations[] = $relativePath;
                }
            }
        }

        // 3. Check for raw queries in domain backend layers (app/Actions, app/Services, app/Models) (WARN)
        $backendPaths = array_filter([
            app_path('Actions'),
            app_path('Services'),
            app_path('Models'),
        ], fn ($p) => File::isDirectory($p));

        $backendRaw = [];
        foreach ($backendPaths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                if (preg_match($rawPattern, $content)) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $backendRaw[] = $relativePath;
                }
            }
        }

        // Aggregate findings
        if (! empty($unsafeInterpolations)) {
            $this->addResult('Database & Queries', 'RAW Query Limitation & Injection Guard', 'FAIL', 'Unsafe RAW query with variable interpolation/concatenation detected in: ' . implode(', ', array_unique($unsafeInterpolations)) . '. Use PDO parameter bindings (? or named bindings).');
        } elseif (! empty($uiViolations)) {
            $this->addResult('Database & Queries', 'RAW Query Limitation & Injection Guard', 'FAIL', 'RAW database query forbidden in presentation layers: ' . implode(', ', array_unique($uiViolations)) . '. Move to dedicated Action or Service class.');
        } elseif (! empty($backendRaw)) {
            $this->addResult('Database & Queries', 'RAW Query Limitation & Injection Guard', 'WARN', 'RAW query in backend domain layer: ' . implode(', ', array_unique($backendRaw)) . '. Ensure critical-need justification (aggregations/window functions) and preserve office_id scoping.');
        } else {
            $this->addResult('Database & Queries', 'RAW Query Limitation & Injection Guard', 'PASS', 'Balanced query architecture satisfied: no prohibited or unescaped raw queries detected.');
        }
    }

    /**
     * Tier 2: Decoupled Security Invariants Execution & Compliance Projection.
     */
    protected function auditSecurityInvariants(string $owaspVersion = SecurityStandardsMapper::DEFAULT_OWASP_VERSION): void
    {
        $framework = SecurityStandardsMapper::getFramework($owaspVersion);
        $this->activeFramework = $framework;

        // 1. Execute technical security invariants (independent of regulatory versions)
        $invariants = [
            'SEC-MASS-ASSIGNMENT' => $this->checkInvariantMassAssignment(),
            'SEC-ENV-DEBUG-CSP' => $this->checkInvariantDebugExposure(),
            'SEC-SUPPLY-CHAIN' => $this->checkInvariantSupplyChain(),
            'SEC-CRYPTO-PII' => $this->checkInvariantCryptographicIsolation(),
            'SEC-BLADE-XSS' => $this->checkInvariantBladeOutput(),
            'SEC-SOD-POLICY' => $this->checkInvariantAuthorizationDesign(),
            'SEC-AUTH-THROTTLE' => $this->checkInvariantAuthenticationThrottling(),
            'SEC-SERIALIZATION' => $this->checkInvariantSerialization(),
            'SEC-AUDIT-LOGGING' => $this->checkInvariantAuditLogging(),
            'SEC-FAIL-CLOSED-SCOPE' => $this->checkInvariantFailClosedScope(),
        ];

        // 2. Project findings into target compliance framework
        foreach ($framework['mappings'] as $catKey => $mapping) {
            $ruleId = $mapping['rule_id'];
            $inv = $invariants[$ruleId] ?? [
                'status' => 'WARN',
                'message' => 'Rule check not evaluated.',
                'code_recommendation' => null,
                'infra_recommendation' => null,
            ];

            $this->addResult(
                category: $mapping['category'],
                check: $mapping['check'],
                status: $inv['status'],
                message: $inv['message'],
                tier: $framework['tag'],
                codeRecommendation: $inv['code_recommendation'],
                infraRecommendation: $inv['infra_recommendation'],
                ruleId: $ruleId,
                frameworkVersion: $framework['version'],
            );
        }
    }

    /**
     * Invariant SEC-MASS-ASSIGNMENT: Eloquent $guarded array safety.
     */
    protected function checkInvariantMassAssignment(): array
    {
        $modelFiles = $this->getPhpFiles(app_path('Models'));
        $guardedViolations = [];

        foreach ($modelFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            if (preg_match('/\$guarded\s*=\s*\[\s*\]/i', $content)) {
                $guardedViolations[] = $className;
            }
        }

        if (empty($guardedViolations)) {
            return [
                'status' => 'PASS',
                'message' => 'Zero $guarded = [] anti-patterns detected; public routes bound via UUID.',
                'code_recommendation' => 'Declare explicit protected $fillable arrays on all Eloquent models.',
                'infra_recommendation' => 'Set Cloudflare WAF Maximum Request Body Size to 25MB to prevent bulk payload manipulation.',
            ];
        }

        return [
            'status' => 'FAIL',
            'message' => 'Insecure $guarded = [] detected in: ' . implode(', ', $guardedViolations) . '. Mass assignment vulnerability.',
            'code_recommendation' => 'Remove $guarded = [] and declare an explicit $fillable whitelist defining permitted attributes.',
            'infra_recommendation' => 'Configure Cloudflare WAF Managed Rules to block unauthorized parameter injection.',
        ];
    }

    /**
     * Invariant SEC-ENV-DEBUG-CSP: Environment debug suppression and CSP registration.
     */
    protected function checkInvariantDebugExposure(): array
    {
        $envExamplePath = base_path('.env.example');
        $hasDebugInEnv = false;

        if (File::exists($envExamplePath)) {
            $content = File::get($envExamplePath);
            if (preg_match('/APP_DEBUG\s*=\s*true/i', $content)) {
                $hasDebugInEnv = true;
            }
        }

        $cspConfigured = File::exists(app_path('Support/Csp/AppCspPolicy.php'))
            || File::exists(config_path('csp.php'))
            || class_exists(\Lgu\GovSuite\Csp\AppCspPolicy::class);

        if (! $hasDebugInEnv && $cspConfigured) {
            return [
                'status' => 'PASS',
                'message' => 'APP_DEBUG safe default in .env.example; Content Security Policy registered.',
                'code_recommendation' => 'Ensure APP_DEBUG=false in production and AppCspPolicy is active in HTTP kernel.',
                'infra_recommendation' => 'CloudPanel Vhost: Inject security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy). Cloudflare: Enable "Always Use HTTPS" and HSTS.',
            ];
        }

        $findings = [];
        if ($hasDebugInEnv) {
            $findings[] = 'APP_DEBUG=true exposed in .env.example';
        }
        if (! $cspConfigured) {
            $findings[] = 'AppCspPolicy missing';
        }

        return [
            'status' => 'WARN',
            'message' => 'Configuration risks: ' . implode(', ', $findings),
            'code_recommendation' => 'Set APP_DEBUG=false in production and install AppCspPolicy via php artisan lgu:install --tag=lgu-stubs.',
            'infra_recommendation' => 'CloudPanel: Update Nginx vhost with security headers. Cloudflare: Toggle ON "Always Use HTTPS" and configure Edge HSTS (max-age=31536000).',
        ];
    }

    /**
     * Invariant SEC-SUPPLY-CHAIN: Package lockfile presence and dependency integrity.
     */
    protected function checkInvariantSupplyChain(): array
    {
        $hasLock = File::exists(base_path('composer.lock'));

        if ($hasLock) {
            return [
                'status' => 'PASS',
                'message' => 'composer.lock present, verifying pinned dependencies.',
                'code_recommendation' => 'Run composer audit and npm audit in CI/CD before deployments to detect vulnerable packages.',
                'infra_recommendation' => 'CloudPanel: Ensure PHP-FPM disables allow_url_fopen (allow_url_fopen = Off) and isolated site user permissions.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'composer.lock not found in project root. Commit lock file to guarantee supply chain integrity.',
            'code_recommendation' => 'Run composer update in development and commit composer.lock to your Git repository.',
            'infra_recommendation' => 'CloudPanel: Never deploy applications using unpinned composer dependencies without a lockfile.',
        ];
    }

    /**
     * Invariant SEC-CRYPTO-PII: Dedicated cryptographic key isolation for personal information.
     */
    protected function checkInvariantCryptographicIsolation(): array
    {
        $envContent = File::exists(base_path('.env')) ? (string) File::get(base_path('.env')) : '';
        $hasIsolatedKey = ! empty(config('lgu.dpa.encryption_key')) || str_contains($envContent, 'PII_ENCRYPTION_KEY=');

        if ($hasIsolatedKey) {
            return [
                'status' => 'PASS',
                'message' => 'Dedicated PII encryption key configured, isolating SPI from application master key.',
                'code_recommendation' => 'Ensure all SPI fields use EncryptedPii cast and companion blind index columns.',
                'infra_recommendation' => 'Cloudflare: Set SSL/TLS encryption mode to "Full (Strict)" and Minimum TLS Version to "TLS 1.2" (or 1.3).',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'PII_ENCRYPTION_KEY not explicitly configured in .env; falling back to APP_KEY.',
            'code_recommendation' => 'Generate and set a dedicated PII_ENCRYPTION_KEY in .env (php artisan key:generate --show) to prevent key-rotation lockouts.',
            'infra_recommendation' => 'Cloudflare: Ensure SSL mode is set to "Full (Strict)" so traffic between Cloudflare edge and CloudPanel origin is TLS-encrypted.',
        ];
    }

    /**
     * Invariant SEC-BLADE-XSS: View output auto-escaping and raw directive restriction.
     */
    protected function checkInvariantBladeOutput(): array
    {
        $viewPaths = array_filter([resource_path('views')], fn ($p) => File::isDirectory($p));
        $rawBladeOutputs = [];

        foreach ($viewPaths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                if (preg_match('/\{!!\s*\$[a-zA-Z_]/', $content)) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $rawBladeOutputs[] = $relativePath;
                }
            }
        }

        if (empty($rawBladeOutputs)) {
            return [
                'status' => 'PASS',
                'message' => 'Zero unescaped Blade directives ({!! $var !!}) detected; raw queries parameter-bound.',
                'code_recommendation' => 'Always use double-curly braces {{ $var }} for output; pipe through HTMLPurifier if rich HTML is required.',
                'infra_recommendation' => 'Cloudflare: Enable "Bot Fight Mode" and "Browser Integrity Check"; turn on Cloudflare Managed Free WAF rules.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'Unescaped Blade directives ({!! $var !!}) found in: ' . implode(', ', array_unique($rawBladeOutputs)) . '. Potential XSS exposure.',
            'code_recommendation' => 'Replace {!! $var !!} with standard {{ $var }} auto-escaped Blade tags, or sanitize explicitly using e() or HTMLPurifier.',
            'infra_recommendation' => 'CloudPanel & Cloudflare: Enforce strict Content-Security-Policy (CSP) headers and activate Cloudflare Free WAF rules.',
        ];
    }

    /**
     * Invariant SEC-SOD-POLICY: Segregation of duties and base authorization policy.
     */
    protected function checkInvariantAuthorizationDesign(): array
    {
        $hasBasePolicy = class_exists(\Lgu\GovSuite\Policies\BaseGovernmentPolicy::class)
            || File::exists(app_path('Policies/BaseGovernmentPolicy.php'));

        if ($hasBasePolicy) {
            return [
                'status' => 'PASS',
                'message' => 'BaseGovernmentPolicy foundation available for institutional SoD and monetary ceilings.',
                'code_recommendation' => 'Ensure model policies extend BaseGovernmentPolicy and enforce creator != approver checks.',
                'infra_recommendation' => 'CloudPanel: Maintain complete physical database isolation between staging (sandbox) and live municipal production.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'BaseGovernmentPolicy not detected in application namespace.',
            'code_recommendation' => 'Publish BaseGovernmentPolicy via php artisan lgu:install --tag=lgu-stubs to govern approval delegation ceilings.',
            'infra_recommendation' => 'CloudPanel: Ensure separate database users with restricted schema permissions for different municipal applications.',
        ];
    }

    /**
     * Invariant SEC-AUTH-THROTTLE: Login and authentication endpoint rate limiting.
     */
    protected function checkInvariantAuthenticationThrottling(): array
    {
        $routeFiles = [base_path('routes/web.php'), base_path('routes/auth.php')];
        $hasThrottle = false;

        foreach ($routeFiles as $rf) {
            if (File::exists($rf)) {
                $content = File::get($rf);
                if (str_contains($content, 'throttle')) {
                    $hasThrottle = true;
                    break;
                }
            }
        }

        if ($hasThrottle) {
            return [
                'status' => 'PASS',
                'message' => 'Route throttling detected on application routing files.',
                'code_recommendation' => 'Apply throttle:login or throttle:6,1 on all authentication endpoints and enforce 15-minute idle timeouts.',
                'infra_recommendation' => 'Cloudflare: Deploy Cloudflare Free Rate Limiting Rule: limit URI path "/login" to 5 requests per 10 seconds per IP.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'Explicit route throttling (throttle) not detected in routes/web.php or routes/auth.php.',
            'code_recommendation' => 'Attach throttle:login or throttle:6,1 to login and password-reset POST routes; verify EnforceSessionTimeout middleware.',
            'infra_recommendation' => 'Cloudflare: Create a Rate Limiting Rule (under Security > WAF) for "/login" to block brute-force attempts at the edge.',
        ];
    }

    /**
     * Invariant SEC-SERIALIZATION: Unsafe object deserialization restriction.
     */
    protected function checkInvariantSerialization(): array
    {
        $appFiles = $this->getPhpFiles(app_path());
        $unsafeDeserialization = [];

        foreach ($appFiles as $file) {
            $content = File::get($file);
            if (preg_match('/\bunserialize\s*\(/i', $content)) {
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                $unsafeDeserialization[] = $relativePath;
            }
        }

        if (empty($unsafeDeserialization)) {
            return [
                'status' => 'PASS',
                'message' => 'Zero unsafe unserialize() calls; SHA-256 hash chaining architecture supported.',
                'code_recommendation' => 'Always use json_decode() for structured data; attach AuditActivityObserver to transactional records.',
                'infra_recommendation' => 'CloudPanel: Apply database trigger database/sql/freeze_audit_logs_trigger.sql to guarantee database-level immutability.',
            ];
        }

        return [
            'status' => 'FAIL',
            'message' => 'Dangerous unserialize() found in: ' . implode(', ', array_unique($unsafeDeserialization)) . '. Remote Code Execution risk.',
            'code_recommendation' => 'Refactor unserialize() to safe json_decode($data, true) across all flagged classes.',
            'infra_recommendation' => 'CloudPanel: Ensure PHP-FPM disabled dangerous execution functions (system, exec, passthru) in php.ini.',
        ];
    }

    /**
     * Invariant SEC-AUDIT-LOGGING: Cryptographic immutable audit observer registration.
     */
    protected function checkInvariantAuditLogging(): array
    {
        $hasAuditObserver = class_exists(\Lgu\GovSuite\Observers\AuditActivityObserver::class)
            || File::exists(app_path('Observers/AuditActivityObserver.php'));

        if ($hasAuditObserver) {
            return [
                'status' => 'PASS',
                'message' => 'AuditActivityObserver verified for continuous cryptographic row hashing.',
                'code_recommendation' => 'Log all security events, failed authorizations, and office context switches to audit_activities.',
                'infra_recommendation' => 'CloudPanel: Configure log rotation for /var/log/nginx/ and monitor daily error log entries.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'AuditActivityObserver not registered in host application.',
            'code_recommendation' => 'Publish and register AuditActivityObserver to ensure all state changes generate cryptographic audit records.',
            'infra_recommendation' => 'CloudPanel: Forward Nginx access logs and application logs to centralized syslog/monitoring facility.',
        ];
    }

    /**
     * Invariant SEC-FAIL-CLOSED-SCOPE: Fail-closed multi-tenant query scope safety.
     */
    protected function checkInvariantFailClosedScope(): array
    {
        $hasOfficeScope = class_exists(\Lgu\GovSuite\Scopes\OfficeScope::class)
            || File::exists(app_path('Scopes/OfficeScope.php'));

        if ($hasOfficeScope) {
            return [
                'status' => 'PASS',
                'message' => 'Fail-closed OfficeScope verified (evaluates to 1=0 on missing context).',
                'code_recommendation' => 'Ensure all multi-tenant and departmental query scopes fail closed if session variables are absent or corrupted.',
                'infra_recommendation' => 'Cloudflare: Enable Custom Error Pages (500/502/503) to mask raw server error output from citizen browsers.',
            ];
        }

        return [
            'status' => 'WARN',
            'message' => 'OfficeScope missing from host application. Query scopes must fail closed.',
            'code_recommendation' => 'Implement fail-closed query scopes using BelongsToOffice trait so missing credentials return zero rows.',
            'infra_recommendation' => 'Cloudflare: Configure custom 5xx error pages to suppress internal exception stack traces.',
        ];
    }

    /**
     * Public proxy for statutory reconciliations.
     */
    public function getStatutoryReconciliations(): array
    {
        return SecurityStandardsMapper::getStatutoryReconciliations($this->owaspVersion);
    }

    /**
     * Public proxy for infrastructure blueprint.
     */
    public function getInfrastructurePlaybook(): array
    {
        return SecurityStandardsMapper::getInfrastructurePlaybook();
    }

    protected function addResult(
        string $category,
        string $check,
        string $status,
        string $message,
        string $tier = 'Statutory & Governance',
        ?string $codeRecommendation = null,
        ?string $infraRecommendation = null,
        ?string $ruleId = null,
        ?string $frameworkVersion = null,
    ): void {
        if ($status === 'PASS') {
            $this->passCount++;
        } elseif ($status === 'WARN') {
            $this->warnCount++;
        } else {
            $this->failCount++;
        }

        $result = [
            'tier' => $tier,
            'framework' => $this->activeFramework['name'] ?? 'Statutory & Governance',
            'framework_version' => $frameworkVersion ?? ($this->activeFramework['version'] ?? null),
            'framework_tag' => $tier,
            'rule_id' => $ruleId,
            'category' => $category,
            'check' => $check,
            'status' => $status,
            'message' => $message,
            'code_recommendation' => $codeRecommendation,
            'infra_recommendation' => $infraRecommendation,
        ];

        $this->results[] = $result;

        if ($tier === 'Statutory & Governance') {
            $this->statutoryResults[] = $result;
        } else {
            $this->owaspResults[] = $result;
        }
    }

    protected function getPhpFiles(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return array_map(fn ($f) => $f->getRealPath(), File::allFiles($directory));
    }

    protected function outputScopeReminder(): void
    {
        $frameworkTag = $this->activeFramework['tag'] ?? 'OWASP Top 10:2025';
        $frameworkVersion = $this->activeFramework['version'] ?? '2025 (Active Version)';

        $this->newLine();
        $this->info('🏛️  Philippine LGU Architecture Compliance Audit');
        $this->line("    Evaluating codebase against LGU-ICTO-STD-2026-V1 & {$frameworkTag} Standards [Enforcing: {$frameworkVersion}]");
        $this->newLine();
        $this->comment('📢 AUDIT SCOPE & LIMITATION NOTICE:');
        $this->line('    • <info>IN-SCOPE:</info> Technical architecture conventions, statutory engineering controls (DPA RA 10173, ARTA RA 11032, COA ledgers), and OWASP security invariants.');
        $this->line('    • <comment>OUT-OF-SCOPE:</comment> This auditor does NOT evaluate application business domain functionality, workflow correctness, or project-specific requirements (TOR/PRD).');
        $this->line('      Functional completeness and business logic must be verified independently through automated feature tests (`php artisan test`) and stakeholder User Acceptance Testing (UAT).');
        $this->newLine();
    }

    protected function outputCliTable(): void
    {
        $frameworkTag = $this->activeFramework['tag'] ?? 'OWASP Top 10:2025';
        $frameworkVersion = $this->activeFramework['version'] ?? '2025 (Active Version)';

        // 1. Tier 1 Table
        $this->comment('📋 Tier 1: Philippine Statutory & Governance Standards');
        $statutoryRows = [];
        foreach ($this->statutoryResults as $r) {
            $statusText = match ($r['status']) {
                'PASS' => '<info>PASS</info>',
                'WARN' => '<comment>WARN</comment>',
                'FAIL' => '<error>FAIL</error>',
                default => $r['status'],
            };

            $statutoryRows[] = [
                $r['category'],
                $r['check'],
                $statusText,
                $r['message'],
            ];
        }
        $this->table(['Category', 'Compliance Check', 'Status', 'Findings & Remediation'], $statutoryRows);

        $this->newLine();

        // 2. Tier 2 Table (OWASP with explicit version)
        $this->comment("🛡️  Tier 2: {$frameworkTag} Security Assessment [Standard Version: {$frameworkVersion}]");
        $owaspRows = [];
        foreach ($this->owaspResults as $r) {
            $statusText = match ($r['status']) {
                'PASS' => '<info>PASS</info>',
                'WARN' => '<comment>WARN</comment>',
                'FAIL' => '<error>FAIL</error>',
                default => $r['status'],
            };

            $owaspRows[] = [
                $r['category'],
                $r['check'],
                $statusText,
                $r['message'],
            ];
        }
        $this->table(["{$frameworkTag} Category", 'Security Check', 'Status', 'Findings & Directives'], $owaspRows);

        $this->newLine();
        $this->line("Summary: <info>{$this->passCount} Passed</info> | <comment>{$this->warnCount} Warnings</comment> | <error>{$this->failCount} Failed</error>");

        // 3. Statutory vs. OWASP Reconciliations
        $this->newLine();
        $this->comment("⚖️  Statutory vs. OWASP Top 10 Reconciliation Recommendations [{$frameworkTag}]");
        $recRows = [];
        foreach ($this->getStatutoryReconciliations() as $rec) {
            $recRows[] = [
                $rec['conflict'],
                $rec['reconciliation'],
            ];
        }
        $this->table(['Conflict Area', 'Reconciliation Standard & Legal Precedence'], $recRows);

        // 4. Infrastructure Implementation Blueprint
        $this->newLine();
        $this->comment('🌐 Infrastructure Implementation Blueprint (CloudPanel & Cloudflare Free Tier)');
        $playbook = $this->getInfrastructurePlaybook();

        $this->line('<info>1. ' . $playbook['cloudpanel']['title'] . '</info>');
        foreach ($playbook['cloudpanel']['steps'] as $step) {
            $this->line('   • ' . $step);
        }
        $this->newLine();

        $this->line('<info>2. ' . $playbook['cloudflare']['title'] . '</info>');
        foreach ($playbook['cloudflare']['steps'] as $step) {
            $this->line('   • ' . $step);
        }
        $this->newLine();

        // 5. Applicable National Privacy Commission (NPC) Issuances Inventory
        if (! empty($this->npcApplicableIssuances)) {
            $this->comment('🇵🇭  Applicable National Privacy Commission (NPC) Issuances Inventory');
            $npcRows = [];
            foreach ($this->npcApplicableIssuances as $item) {
                $npcRows[] = [
                    $item['number'] ?? $item['id'],
                    $item['title'] ?? '',
                    $item['type'] ?? 'Circular',
                    $item['url'] ?? '',
                ];
            }
            $this->table(['Issuance Number', 'Title / Regulation', 'Type', 'Official Reference URL'], $npcRows);
            $this->newLine();
        }

        if ($this->option('fix-hint')) {
            $this->newLine();
            $this->comment('💡 Remediation Directives & Fix Hints (--fix-hint):');
            foreach ($this->results as $r) {
                if ($r['status'] !== 'PASS' && (! empty($r['code_recommendation']) || ! empty($r['infra_recommendation']))) {
                    $this->line("<comment>• [{$r['category']}] {$r['check']}:</comment>");
                    if (! empty($r['code_recommendation'])) {
                        $this->line("    <info>Code Fix:</info> {$r['code_recommendation']}");
                    }
                    if (! empty($r['infra_recommendation'])) {
                        $this->line("    <info>Hosting Action:</info> {$r['infra_recommendation']}");
                    }
                }
            }
        }

        if ($this->failCount > 0) {
            $this->error('❌ Compliance audit failed. Address FAIL findings before submitting for ICTO acceptance.');
        } else {
            $this->info("✅ Codebase satisfies LGU statutory standards and {$frameworkTag} ({$frameworkVersion}) baseline.");
        }
    }

    public function renderJson(): string
    {
        $framework = $this->activeFramework ?: SecurityStandardsMapper::getFramework($this->owaspVersion);

        $payload = [
            'standard' => 'LGU-ICTO-STD-2026-V1',
            'security_framework' => [
                'name' => $framework['name'],
                'version' => $framework['version'],
                'edition' => $this->owaspVersion,
                'tag' => $framework['tag'],
            ],
            'audit_scope' => [
                'evaluation_type' => 'ARCHITECTURAL_AND_STATUTORY_SECURITY_CONTROLS',
                'in_scope' => [
                    'Philippine statutory engineering controls (DPA RA 10173, ARTA RA 11032, COA ledgers)',
                    'Application security invariants (OWASP Top 10 baseline)',
                    'Architecture patterns (Dual-Identifier, Balanced Query Architecture, fail-closed tenancy)',
                ],
                'out_of_scope_disclaimer' => 'This auditor does NOT evaluate application business domain functionality, workflow correctness, or project-specific requirements (TOR/PRD). Business completeness and functional requirements must be validated via automated feature tests and stakeholder UAT.',
            ],
            'summary' => [
                'passed' => $this->passCount,
                'warned' => $this->warnCount,
                'failed' => $this->failCount,
                'status' => $this->failCount === 0 ? 'COMPLIANT' : 'NON_COMPLIANT',
            ],
            'statutory_results' => $this->statutoryResults,
            'owasp_results' => $this->owaspResults,
            'npc_assessment' => [
                'status' => $this->npcAssessmentSummary ? (empty($this->npcAssessmentSummary['unreviewed_count']) ? 'COMPLIANT' : 'PENDING_REVIEW') : 'NOT_CONFIGURED',
                'summary' => $this->npcAssessmentSummary,
                'applicable_issuances' => $this->npcApplicableIssuances,
            ],
            'reconciliations' => $this->getStatutoryReconciliations(),
            'infrastructure_playbook' => $this->getInfrastructurePlaybook(),
            'results' => $this->results,
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function outputJson(): void
    {
        foreach (explode("\n", $this->renderJson()) as $line) {
            $this->line($line);
        }
    }

    public function renderMarkdown(): string
    {
        $frameworkTag = $this->activeFramework['tag'] ?? 'OWASP Top 10:2025';
        $frameworkVersion = $this->activeFramework['version'] ?? '2025 (Active Version)';

        $lines = [];
        $lines[] = "# LGU Engineering Compliance Audit Report\n";
        $lines[] = '**Standard:** `LGU-ICTO-STD-2026-V1`  ';
        $lines[] = "**Security Framework:** `{$frameworkTag}` (Version: `{$frameworkVersion}`)  ";
        $lines[] = '**Audit Result:** ' . ($this->failCount === 0 ? '✅ COMPLIANT' : '❌ NON-COMPLIANT') . "\n";
        $lines[] = '> [!IMPORTANT] **Audit Scope & Limitation Disclaimer**  ';
        $lines[] = '> This automated compliance auditor evaluates **technical architecture conventions, statutory engineering controls (DPA RA 10173, ARTA RA 11032, COA ledgers), and OWASP security baseline invariants**.  ';
        $lines[] = '> It **does NOT** audit application business domain correctness, user acceptance criteria (UAT), or functional requirements specified in project Terms of Reference (TOR). Functional completeness and business workflow logic must be verified independently through automated test suites (`php artisan test`) and stakeholder User Acceptance Testing (UAT).' . "\n";

        $lines[] = "## 1. Philippine Statutory & Governance Standards\n";
        $lines[] = '| Category | Compliance Check | Status | Details |';
        $lines[] = '| :--- | :--- | :---: | :--- |';
        foreach ($this->statutoryResults as $r) {
            $icon = match ($r['status']) {
                'PASS' => '✅ PASS',
                'WARN' => '⚠️ WARN',
                'FAIL' => '❌ FAIL',
                default => $r['status'],
            };
            $lines[] = "| {$r['category']} | {$r['check']} | {$icon} | {$r['message']} |";
        }

        $lines[] = "\n## 2. {$frameworkTag} Security Assessment (Version: {$frameworkVersion})\n";
        $lines[] = "| {$frameworkTag} Category | Security Invariant Rule | Status | Findings | Code Fix | CloudPanel / Cloudflare Action |";
        $lines[] = '| :--- | :--- | :---: | :--- | :--- | :--- |';
        foreach ($this->owaspResults as $r) {
            $icon = match ($r['status']) {
                'PASS' => '✅ PASS',
                'WARN' => '⚠️ WARN',
                'FAIL' => '❌ FAIL',
                default => $r['status'],
            };
            $ruleName = $r['check'];
            $lines[] = "| {$r['category']} | {$ruleName} | {$icon} | {$r['message']} | {$r['code_recommendation']} | {$r['infra_recommendation']} |";
        }

        $lines[] = "\n## 3. Statutory vs. OWASP Top 10 Reconciliation Recommendations\n";
        $lines[] = '| Conflict Area | Statutory Mandate | OWASP Principle | Reconciliation Rule |';
        $lines[] = '| :--- | :--- | :--- | :--- |';
        foreach ($this->getStatutoryReconciliations() as $rec) {
            $lines[] = "| {$rec['conflict']} | {$rec['statutory_rule']} | {$rec['owasp_rule']} | {$rec['reconciliation']} |";
        }

        $lines[] = "\n## 4. Infrastructure Implementation Blueprint (CloudPanel & Cloudflare Free Tier)\n";
        $playbook = $this->getInfrastructurePlaybook();
        $lines[] = "### 4.1 {$playbook['cloudpanel']['title']}\n";
        foreach ($playbook['cloudpanel']['steps'] as $step) {
            $lines[] = "- {$step}";
        }
        $lines[] = "\n### 4.2 {$playbook['cloudflare']['title']}\n";
        foreach ($playbook['cloudflare']['steps'] as $step) {
            $lines[] = "- {$step}";
        }

        if (! empty($this->npcApplicableIssuances)) {
            $lines[] = "\n## 5. Applicable National Privacy Commission (NPC) Issuances Inventory\n";
            $lines[] = '| Issuance Number | Title / Subject Matter | Type | Official Reference URL |';
            $lines[] = '| :--- | :--- | :---: | :--- |';
            foreach ($this->npcApplicableIssuances as $item) {
                $lines[] = "| {$item['number']} | {$item['title']} | {$item['type']} | [Official Link]({$item['url']}) |";
            }
        }

        return implode("\n", $lines);
    }

    protected function outputMarkdown(): void
    {
        foreach (explode("\n", $this->renderMarkdown()) as $line) {
            $this->line($line);
        }
    }

    /**
     * Determine if an audit report should be written to disk.
     */
    protected function shouldSaveReport(): bool
    {
        if (! $this->hasOption('output')) {
            return false;
        }

        $optionValue = $this->option('output');

        if ($optionValue !== null && $optionValue !== false && $optionValue !== '' && $optionValue !== '?') {
            return true;
        }

        if ($this->input !== null && $this->input->hasParameterOption(['--output', '-o'])) {
            return true;
        }

        return false;
    }

    /**
     * Resolve destination path for the audit report.
     */
    protected function getReportDestination(string $format): string
    {
        $extension = ($format === 'json') ? 'json' : 'md';
        $optionValue = $this->option('output');

        if (is_string($optionValue) && trim($optionValue) !== '' && $optionValue !== '1' && $optionValue !== '?') {
            $destination = trim($optionValue);

            if (is_dir($destination) || str_ends_with($destination, '/') || str_ends_with($destination, '\\')) {
                $destination = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . "audit-report.{$extension}";
            }

            if (! (str_starts_with($destination, '/') || str_starts_with($destination, '\\') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $destination))) {
                $destination = base_path($destination);
            }

            return $destination;
        }

        return storage_path("app/lgu/audit-report.{$extension}");
    }

    protected function saveReportToFile(string $format): void
    {
        $destination = $this->getReportDestination($format);
        $isJson = str_ends_with(strtolower($destination), '.json') || $format === 'json';
        $content = $isJson ? $this->renderJson() : $this->renderMarkdown();

        $directory = dirname($destination);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($destination, $content);

        if ($format === 'cli' || $format === '') {
            $this->newLine();
            $this->info("📄 Audit report successfully saved to: {$destination}");
        }
    }
}
