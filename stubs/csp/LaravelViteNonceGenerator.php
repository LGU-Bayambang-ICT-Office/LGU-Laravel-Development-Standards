<?php

namespace App\Support\Csp;

use Illuminate\Support\Facades\Vite;
use Spatie\Csp\Nonce\NonceGenerator;

/**
 * Nonce Generator for Vite & Livewire
 *
 * Bridges Laravel's native Vite CSP nonce mechanism with Spatie CSP headers.
 */
class LaravelViteNonceGenerator implements NonceGenerator
{
    public function generate(): string
    {
        return Vite::useCspNonce();
    }
}
