<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Tests;

class LguAuditCommandTest extends TestCase
{
    public function test_audit_command_runs_successfully(): void
    {
        $this->artisan('lgu:audit')
            ->expectsOutputToContain('Philippine LGU Architecture Compliance Audit')
            ->expectsOutputToContain('RAW Query Limitation & Injection Guard')
            ->assertSuccessful();
    }

    public function test_audit_command_supports_json_format(): void
    {
        $this->artisan('lgu:audit', ['--format' => 'json'])
            ->expectsOutputToContain('LGU-ICTO-STD-2026-V1')
            ->expectsOutputToContain('RAW Query Limitation & Injection Guard')
            ->assertSuccessful();
    }

    public function test_audit_command_supports_markdown_format(): void
    {
        $this->artisan('lgu:audit', ['--format' => 'markdown'])
            ->expectsOutputToContain('# LGU Engineering Compliance Audit Report')
            ->expectsOutputToContain('RAW Query Limitation & Injection Guard')
            ->assertSuccessful();
    }
}
