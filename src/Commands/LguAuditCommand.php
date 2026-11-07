<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * LGU Engineering Standards Compliance Auditor.
 *
 * @author Ricky Bulalakaw <bulalakawr@bayambang.gov.ph>
 */
class LguAuditCommand extends Command
{
    protected $signature = 'lgu:audit 
                            {--format=cli : Output format (cli, json, markdown)}
                            {--strict : Treat warnings as failures}
                            {--fix-hint : Display statutory remediation steps for each finding}';

    protected $description = 'Audit host application for compliance with Philippine LGU Laravel Engineering Standards';

    protected array $results = [];
    protected int $failCount = 0;
    protected int $warnCount = 0;
    protected int $passCount = 0;

    public function handle(): int
    {
        $format = (string) $this->option('format');

        $this->auditCodeQualityPresets();
        $this->auditModelsDualIdentifier();
        $this->auditPiiFieldEncryption();
        $this->auditModelPolicyCoverage();
        $this->auditHardcodedRoleAntiPattern();
        $this->auditEventAfterCommitSafety();
        $this->auditPrintableControllerSeparation();

        if ($format === 'json') {
            $this->outputJson();
        } elseif ($format === 'markdown') {
            $this->outputMarkdown();
        } else {
            $this->outputCliTable();
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
            if (! $hasPint) $missing[] = 'pint.json';
            if (! $hasPhpStan) $missing[] = 'phpstan.neon';
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
            $this->addResult('Data Privacy (RA 10173)', 'SPI At-Rest Field Encryption', 'PASS', 'No unencrypted sensitive personal fields detected in model definitions.');
        } else {
            $details = [];
            foreach ($flaggedModels as $model => $fields) {
                $details[] = "{$model} (" . implode(', ', array_unique($fields)) . ")";
            }
            $this->addResult('Data Privacy (RA 10173)', 'SPI At-Rest Field Encryption', 'FAIL', 'Unencrypted SPI fields: ' . implode('; ', $details) . '. Apply EncryptedPii cast.');
        }
    }

    /**
     * Check Section 15.2: Every Domain Model has an Eloquent Policy.
     */
    protected function auditModelPolicyCoverage(): void
    {
        $modelFiles = $this->getPhpFiles(app_path('Models'));
        $missingPolicies = [];

        foreach ($modelFiles as $file) {
            $className = basename($file, '.php');
            if (in_array($className, ['User', 'Office'], true)) {
                continue;
            }

            $policyPath = app_path("Policies/{$className}Policy.php");
            if (! File::exists($policyPath)) {
                $missingPolicies[] = $className;
            }
        }

        if (empty($missingPolicies)) {
            $this->addResult('Authorization', 'Policy Coverage Mandate', 'PASS', 'All domain models have corresponding Eloquent Policy classes.');
        } else {
            $this->addResult('Authorization', 'Policy Coverage Mandate', 'FAIL', 'Missing Policy for: ' . implode(', ', $missingPolicies));
        }
    }

    /**
     * Check Section 15.4: Cardinal Anti-Pattern scan for hardcoded $user->hasRole() or @role().
     */
    protected function auditHardcodedRoleAntiPattern(): void
    {
        $scanPaths = array_filter([
            app_path('Http/Controllers'),
            app_path('Livewire'),
            resource_path('views'),
        ], fn ($p) => File::isDirectory($p));

        $violations = [];

        foreach ($scanPaths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());

                if (preg_match_all('/(\$user->hasRole\(|@role\(|auth\(\)->user\(\)->hasRole\()/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $violations[] = $relativePath;
                }
            }
        }

        if (empty($violations)) {
            $this->addResult('Authorization', 'Policy-Mediated RBAC (No Hardcoded Roles)', 'PASS', 'Zero hardcoded hasRole() or @role() occurrences detected.');
        } else {
            $this->addResult('Authorization', 'Policy-Mediated RBAC (No Hardcoded Roles)', 'FAIL', 'Hardcoded role checks in: ' . implode(', ', array_unique($violations)) . '. Use $user->can() with Policy.');
        }
    }

    /**
     * Check Section 5.3: Domain events must declare public bool $afterCommit = true.
     */
    protected function auditEventAfterCommitSafety(): void
    {
        $eventFiles = $this->getPhpFiles(app_path('Events'));
        if (empty($eventFiles)) {
            $this->addResult('Event Architecture', 'Transactional Event afterCommit Guard', 'PASS', 'No custom events registered or all events conformant.');
            return;
        }

        $missingGuard = [];

        foreach ($eventFiles as $file) {
            $content = File::get($file);
            $className = basename($file, '.php');

            if (! str_contains($content, '$afterCommit')) {
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

    protected function addResult(string $category, string $check, string $status, string $message): void
    {
        if ($status === 'PASS') {
            $this->passCount++;
        } elseif ($status === 'WARN') {
            $this->warnCount++;
        } else {
            $this->failCount++;
        }

        $this->results[] = [
            'category' => $category,
            'check'    => $check,
            'status'   => $status,
            'message'  => $message,
        ];
    }

    protected function getPhpFiles(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return array_map(fn ($f) => $f->getRealPath(), File::allFiles($directory));
    }

    protected function outputCliTable(): void
    {
        $this->newLine();
        $this->info('🏛️  Philippine LGU Architecture Compliance Audit');
        $this->line('    Evaluating codebase against LGU-ICTO-STD-2026-V1 Standards');
        $this->newLine();

        $rows = [];
        foreach ($this->results as $r) {
            $statusText = match ($r['status']) {
                'PASS' => '<info>PASS</info>',
                'WARN' => '<comment>WARN</comment>',
                'FAIL' => '<error>FAIL</error>',
                default => $r['status'],
            };

            $rows[] = [
                $r['category'],
                $r['check'],
                $statusText,
                $r['message'],
            ];
        }

        $this->table(['Category', 'Compliance Check', 'Status', 'Findings & Remediation'], $rows);

        $this->newLine();
        $this->line("Summary: <info>{$this->passCount} Passed</info> | <comment>{$this->warnCount} Warnings</comment> | <error>{$this->failCount} Failed</error>");

        if ($this->failCount > 0) {
            $this->error('❌ Compliance audit failed. Address FAIL findings before submitting for ICTO acceptance.');
        } else {
            $this->info('✅ Codebase satisfies core LGU statutory and architectural standards.');
        }
    }

    protected function outputJson(): void
    {
        $payload = [
            'standard'   => 'LGU-ICTO-STD-2026-V1',
            'summary'    => [
                'passed' => $this->passCount,
                'warned' => $this->warnCount,
                'failed' => $this->failCount,
                'status' => $this->failCount === 0 ? 'COMPLIANT' : 'NON_COMPLIANT',
            ],
            'results'    => $this->results,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function outputMarkdown(): void
    {
        $this->line("# LGU Engineering Compliance Audit Report\n");
        $this->line("**Standard:** `LGU-ICTO-STD-2026-V1`  ");
        $this->line("**Audit Result:** " . ($this->failCount === 0 ? "✅ COMPLIANT" : "❌ NON-COMPLIANT") . "\n");
        $this->line("| Category | Compliance Check | Status | Details |");
        $this->line("| :--- | :--- | :---: | :--- |");

        foreach ($this->results as $r) {
            $icon = match ($r['status']) {
                'PASS' => '✅ PASS',
                'WARN' => '⚠️ WARN',
                'FAIL' => '❌ FAIL',
                default => $r['status'],
            };
            $this->line("| {$r['category']} | {$r['check']} | {$icon} | {$r['message']} |");
        }
    }
}
