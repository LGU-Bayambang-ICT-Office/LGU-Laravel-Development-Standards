<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * LGU GovSuite Asset Installer.
 *
 * @author Ricky Bulalakaw <bulalakawr@bayambang.gov.ph>
 */
class LguInstallCommand extends Command
{
    protected $signature = 'lgu:install 
                            {--all : Publish all assets without interactive prompts}
                            {--force : Overwrite existing published files}';

    protected $description = 'Install LGU development protocols, code quality configs, AI audit rules, and compliance assets';

    public function handle(): int
    {
        $this->info('🏛️  Local Government Unit (LGU) Development Protocols Installer');
        $this->line('    Targeting Philippine Statutory Standards: DPA RA 10173, ARTA RA 11032, COA, NAP, CSC');
        $this->newLine();

        $force = (bool) $this->option('force');

        if ($this->option('all')) {
            $tags = ['lgu-config', 'lgu-dev-tools', 'lgu-docs', 'lgu-ai-rules', 'lgu-migrations'];
        } else {
            $tags = $this->choice(
                'Select assets to publish into your Laravel application (comma-separated for multiple):',
                [
                    'lgu-config'     => 'Agency Configuration (config/lgu.php)',
                    'lgu-dev-tools'  => 'Code Quality Presets (pint.json, phpstan.neon)',
                    'lgu-ai-rules'   => 'AI Assistant Compliance Rules (.cursorrules, CLAUDE.md, .gemini/rules)',
                    'lgu-docs'       => 'Master Standard Handbook & Vendor Delivery Checklist (docs/)',
                    'lgu-migrations' => 'COA Audit Activity Table & Database Triggers',
                    'lgu-stubs'      => 'Customizable PHP Stubs (app/Casts, app/Traits, etc.)',
                ],
                'lgu-config,lgu-dev-tools,lgu-ai-rules,lgu-docs',
                multiple: true
            );
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--tag'   => $tag,
                '--force' => $force,
            ]);
        }

        $this->newLine();
        $this->info('✅ LGU GovSuite successfully installed!');
        $this->line('👉 Next steps:');
        $this->line('   1. Review config/lgu.php and set your AGENCY_* environment variables in .env');
        $this->line('   2. Run `php artisan lgu:audit` to check your codebase for statutory compliance');
        $this->line('   3. Run `./vendor/bin/pint --test` and `./vendor/bin/phpstan analyse`');

        return self::SUCCESS;
    }
}
