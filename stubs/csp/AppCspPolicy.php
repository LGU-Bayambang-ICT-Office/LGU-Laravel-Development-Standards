<?php
namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * Class AppCspPolicy
 *
 * Implements strict Content Security Policy (CSP) tailored for:
 * - Livewire v3 / v4 dynamic dom updates
 * - Tailwind CSS & Flowbite UI components
 * - Vite dev server hot reloading (in local environment)
 * - CSP violation reporting endpoint
 */
class AppCspPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                Keyword::UNSAFE_EVAL, // Required for Livewire morphdom expressions
            ])
            ->add(Directive::STYLE, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE, // Required for Livewire inline transitions and dynamic styling
                'https://fonts.bunny.net',
                'https://fonts.googleapis.com',
            ])
            ->add(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'blob:',
            ])
            ->add(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.bunny.net',
                'https://fonts.gstatic.com',
                'data:',
            ])
            ->add(Directive::CONNECT, [
                Keyword::SELF,
            ])
            ->add(Directive::FRAME, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->addNonce(Directive::SCRIPT);

        // Allow Vite local development server and WebSockets in non-production
        if (app()->environment('local')) {
            $policy->add(Directive::SCRIPT, 'http://localhost:*');
            $policy->add(Directive::SCRIPT, 'http://127.0.0.1:*');
            $policy->add(Directive::CONNECT, 'ws://localhost:*');
            $policy->add(Directive::CONNECT, 'ws://127.0.0.1:*');
        }

        // Optional violation report URI
        try {
            if (\Illuminate\Support\Facades\Route::has('csp.report')) {
                $policy->setReportUri(route('csp.report'));
            }
        } catch (\Throwable $e) {
            // Ignored during early bootstrap or console commands
        }
    }
}
