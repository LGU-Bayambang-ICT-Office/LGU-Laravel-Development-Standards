<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Tests;

class LguInstallCommandTest extends TestCase
{
    public function test_install_command_runs_with_all_flag(): void
    {
        $this->artisan('lgu:install', ['--all' => true])
            ->expectsOutputToContain('LGU GovSuite successfully installed')
            ->assertSuccessful();
    }

    public function test_install_command_runs_with_ai_skills_flag(): void
    {
        $this->artisan('lgu:install', ['--ai-skills' => true])
            ->expectsOutputToContain('LGU AI Skills successfully published to .agents/skills/')
            ->assertSuccessful();
    }

    public function test_install_command_in_non_interactive_fallback_mode(): void
    {
        $options = [
            'lgu-config' => 'Agency Configuration (config/lgu.php)',
            'lgu-dev-tools' => 'Code Quality Presets (pint.json, phpstan.neon)',
            'lgu-ai-rules' => 'AI Assistant Compliance Rules (.cursorrules, CLAUDE.md, .gemini/rules)',
            'lgu-ai-skills' => 'AI Agent Skills Suite (.agents/skills/ for Antigravity, MCP & AI Agents)',
            'lgu-docs' => 'Master Standard Handbook & Vendor Delivery Checklist (docs/)',
            'lgu-migrations' => 'COA Audit Activity Table & Database Triggers',
            'lgu-stubs' => 'Customizable PHP Stubs (app/Casts, app/Traits, etc.)',
        ];

        $this->artisan('lgu:install')
            ->expectsChoice(
                'Select assets to publish into your Laravel application:',
                ['lgu-config', 'lgu-docs'],
                $options,
            )
            ->expectsOutputToContain('LGU GovSuite successfully installed')
            ->assertSuccessful();
    }
}
