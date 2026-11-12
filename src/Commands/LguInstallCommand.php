<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Commands;

use Illuminate\Console\Command;

/**
 * LGU GovSuite Asset Installer.
 *
 * @author Ricky Bulalakaw
 */
class LguInstallCommand extends Command
{
    protected $signature = 'lgu:install 
                            {--all : Publish all assets without interactive prompts}
                            {--ai-skills : Publish AI agent skills (.agents/skills/) only}
                            {--force : Overwrite existing published files}';

    protected $description = 'Install LGU development protocols, code quality configs, AI skills, and compliance assets';

    public function handle(): int
    {
        $this->info('🏛️  Local Government Unit (LGU) Development Protocols Installer');
        $this->line('    Targeting Philippine Statutory Standards: DPA RA 10173, ARTA RA 11032, COA, NAP, CSC');
        $this->newLine();

        $force = (bool) $this->option('force');

        if ($this->option('ai-skills')) {
            $this->call('vendor:publish', [
                '--tag' => 'lgu-ai-skills',
                '--force' => $force,
            ]);
            $this->info('🤖 LGU AI Skills successfully published to .agents/skills/!');

            return self::SUCCESS;
        }

        $options = [
            'lgu-config' => 'Agency Configuration (config/lgu.php)',
            'lgu-dev-tools' => 'Code Quality Presets (pint.json, phpstan.neon)',
            'lgu-ai-rules' => 'AI Assistant Compliance Rules (.cursorrules, CLAUDE.md, .gemini/rules)',
            'lgu-ai-skills' => 'AI Agent Skills Suite (.agents/skills/ for Antigravity, MCP & AI Agents)',
            'lgu-docs' => 'Master Standard Handbook & Vendor Delivery Checklist (docs/)',
            'lgu-migrations' => 'COA Audit Activity Table & Database Triggers',
            'lgu-stubs' => 'Customizable PHP Stubs (app/Casts, app/Traits, etc.)',
        ];

        $defaultSelection = ['lgu-config', 'lgu-dev-tools', 'lgu-ai-rules', 'lgu-ai-skills', 'lgu-docs'];

        if ($this->option('all')) {
            $tags = array_keys($options);
        } elseif (function_exists('Laravel\Prompts\multiselect')) {
            $tags = \Laravel\Prompts\multiselect(
                label: 'Select assets to publish into your Laravel application:',
                options: $options,
                default: $defaultSelection,
                hint: 'Use arrow keys to navigate, Space to select/deselect, and Enter to submit.',
            );
        } else {
            $tags = (array) $this->choice(
                'Select assets to publish into your Laravel application (comma-separated for multiple):',
                $options,
                implode(',', $defaultSelection),
                multiple: true,
            );
        }

        if (empty($tags)) {
            $this->warn('No assets selected. Installation skipped.');

            return self::SUCCESS;
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => $force,
            ]);
        }

        $this->newLine();
        $this->info('✅ LGU GovSuite successfully installed!');
        $this->line('👉 Next steps:');
        $this->line('   1. Review config/lgu.php and set your AGENCY_* environment variables in .env');
        $this->line('   2. Run `php artisan lgu:audit` or trigger the `lgu-audit-reviewer` AI skill');
        $this->line('   3. Run `./vendor/bin/pint --test` and `./vendor/bin/phpstan analyse`');

        return self::SUCCESS;
    }
}
