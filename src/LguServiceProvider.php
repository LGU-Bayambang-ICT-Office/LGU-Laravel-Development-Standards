<?php

declare(strict_types=1);

namespace Lgu\GovSuite;

use Illuminate\Support\ServiceProvider;
use Lgu\GovSuite\Commands\LguAuditCommand;
use Lgu\GovSuite\Commands\LguInstallCommand;

/**
 * LGU GovSuite Service Provider.
 *
 * @author Ricky Bulalakaw <bulalakawr@bayambang.gov.ph>
 * @copyright 2026 Information and Communications Technology Office (ICTO), Municipality of Bayambang
 */
class LguServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lgu.php', 'lgu');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Register Artisan Commands
            $this->commands([
                LguInstallCommand::class,
                LguAuditCommand::class,
            ]);

            // 1. Publish Configuration
            $this->publishes([
                __DIR__ . '/../config/lgu.php' => config_path('lgu.php'),
            ], 'lgu-config');

            // 2. Publish Code Quality Presets (Pint & Larastan)
            $this->publishes([
                __DIR__ . '/../pint.json' => base_path('pint.json'),
                __DIR__ . '/../phpstan.neon' => base_path('phpstan.neon'),
            ], 'lgu-dev-tools');

            // 3. Publish AI Rules (.cursorrules, CLAUDE.md, .gemini/rules/lgu.md, copilot-instructions.md)
            $this->publishes([
                __DIR__ . '/../resources/ai-rules/.cursorrules' => base_path('.cursorrules'),
                __DIR__ . '/../resources/ai-rules/CLAUDE.md' => base_path('CLAUDE.md'),
                __DIR__ . '/../resources/ai-rules/lgu.md' => base_path('.gemini/rules/lgu.md'),
                __DIR__ . '/../resources/ai-rules/copilot-instructions.md' => base_path('.github/copilot-instructions.md'),
            ], 'lgu-ai-rules');

            // 4. Publish Handbooks & Statutory Acceptance Checklist
            $this->publishes([
                __DIR__ . '/../LARAVEL_LGU_DEVELOPMENT_STANDARDS.md' => base_path('docs/LARAVEL_LGU_DEVELOPMENT_STANDARDS.md'),
                __DIR__ . '/../VENDOR_DELIVERY_CHECKLIST.md' => base_path('docs/VENDOR_DELIVERY_CHECKLIST.md'),
            ], 'lgu-docs');

            // 5. Publish Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'lgu-migrations');

            // 6. Publish Stubs into app/ for teams wanting local customization
            $this->publishes([
                __DIR__ . '/../stubs/casts' => app_path('Casts'),
                __DIR__ . '/../stubs/csp' => app_path('Support/Csp'),
                __DIR__ . '/../stubs/middleware' => app_path('Http/Middleware'),
                __DIR__ . '/../stubs/observers' => app_path('Observers'),
                __DIR__ . '/../stubs/policies' => app_path('Policies'),
                __DIR__ . '/../stubs/scopes' => app_path('Scopes'),
                __DIR__ . '/../stubs/services' => app_path('Services'),
                __DIR__ . '/../stubs/traits' => app_path('Traits'),
            ], 'lgu-stubs');
        }
    }
}
