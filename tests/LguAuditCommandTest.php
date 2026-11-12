<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Tests;

use Illuminate\Support\Facades\File;

class LguAuditCommandTest extends TestCase
{
    public function test_audit_command_runs_successfully(): void
    {
        $this->artisan('lgu:audit')
            ->expectsOutputToContain('Philippine LGU Architecture Compliance Audit')
            ->expectsOutputToContain('AUDIT SCOPE & LIMITATION NOTICE')
            ->expectsOutputToContain('does NOT evaluate application business domain functionality')
            ->expectsOutputToContain('Tier 1: Philippine Statutory & Governance Standards')
            ->expectsOutputToContain('Tier 2: OWASP Top 10:2025 Security Assessment')
            ->expectsOutputToContain('2025 (Active Version)')
            ->expectsOutputToContain('Statutory vs. OWASP Top 10 Reconciliation Recommendations')
            ->expectsOutputToContain('CloudPanel & Cloudflare Free Tier')
            ->assertSuccessful();
    }

    public function test_audit_command_supports_json_format(): void
    {
        $this->artisan('lgu:audit', ['--format' => 'json'])
            ->expectsOutputToContain('LGU-ICTO-STD-2026-V1')
            ->expectsOutputToContain('OWASP Top 10:2025')
            ->expectsOutputToContain('2025 (Active Version)')
            ->expectsOutputToContain('audit_scope')
            ->expectsOutputToContain('out_of_scope_disclaimer')
            ->expectsOutputToContain('statutory_results')
            ->expectsOutputToContain('owasp_results')
            ->expectsOutputToContain('npc_assessment')
            ->expectsOutputToContain('reconciliations')
            ->expectsOutputToContain('infrastructure_playbook')
            ->assertSuccessful();
    }

    public function test_audit_command_supports_markdown_format(): void
    {
        $this->artisan('lgu:audit', ['--format' => 'markdown'])
            ->expectsOutputToContain('# LGU Engineering Compliance Audit Report')
            ->expectsOutputToContain('Audit Scope & Limitation Disclaimer')
            ->expectsOutputToContain('does NOT audit application business domain correctness')
            ->expectsOutputToContain('## 1. Philippine Statutory & Governance Standards')
            ->expectsOutputToContain('## 2. OWASP Top 10:2025 Security Assessment (Version: 2025 (Active Version))')
            ->expectsOutputToContain('## 3. Statutory vs. OWASP Top 10 Reconciliation Recommendations')
            ->expectsOutputToContain('## 4. Infrastructure Implementation Blueprint')
            ->assertSuccessful();
    }

    public function test_audit_command_supports_framework_version_switching(): void
    {
        $this->artisan('lgu:audit', ['--owasp-version' => '2021'])
            ->expectsOutputToContain('Tier 2: OWASP Top 10:2021 Security Assessment')
            ->expectsOutputToContain('2021 (Historical Benchmark)')
            ->assertSuccessful();
    }

    public function test_audit_command_reports_npc_issuances_inventory(): void
    {
        $path = storage_path('app/lgu/npc-assessment.json');
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, json_encode([
            'updated_at' => date('c'),
            'total_issuances' => 1,
            'applicable_count' => 1,
            'issuances' => [
                [
                    'id' => 'NPC-CIRC-2016-01',
                    'type' => 'Circular',
                    'number' => 'NPC Circular No. 16-01',
                    'title' => 'Security of Personal Data in Government Agencies',
                    'status' => 'APPLICABLE',
                    'url' => 'https://privacy.gov.ph/wp-content/uploads/2022/01/Sgd-NPC-Circular-16-01-Security-of-Personal-Data-in-Government-Agencies.pdf',
                ],
            ],
        ]));

        $this->artisan('lgu:audit')
            ->expectsOutputToContain('Applicable National Privacy Commission (NPC) Issuances Inventory')
            ->expectsOutputToContain('NPC Circular No. 16-01')
            ->assertSuccessful();

        File::delete($path);
    }

    public function test_audit_command_saves_report_to_file_via_output_option(): void
    {
        $defaultPath = storage_path('app/lgu/audit-report.md');
        if (File::exists($defaultPath)) {
            File::delete($defaultPath);
        }

        $this->artisan('lgu:audit', ['--output' => true])
            ->expectsOutputToContain('Audit report successfully saved to:')
            ->assertSuccessful();

        $this->assertTrue(File::exists($defaultPath));
        $content = File::get($defaultPath);
        $this->assertStringContainsString('# LGU Engineering Compliance Audit Report', $content);
        $this->assertStringContainsString('OWASP Top 10:2025', $content);

        File::delete($defaultPath);
    }

    public function test_audit_command_displays_fix_hints(): void
    {
        $this->artisan('lgu:audit', ['--fix-hint' => true])
            ->expectsOutputToContain('Remediation Directives & Fix Hints')
            ->assertSuccessful();
    }
}
