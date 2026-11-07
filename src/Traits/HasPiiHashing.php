<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Traits;

use Illuminate\Support\Facades\Config;

/**
 * Trait HasPiiHashing
 *
 * Implements the Blind Index Pattern for searching encrypted PII fields under RA 10173.
 * Storing a salted HMAC-SHA256 hash in a companion column allows fast, indexed SQL exact-match
 * queries without compromising AES-256 ciphertext security.
 */
trait HasPiiHashing
{
    /**
     * Compute a normalized blind index hash for an exact-match searchable PII field.
     */
    public static function hashPii(?string $value): ?string
    {
        if (is_null($value) || trim($value) === '') {
            return null;
        }

        $salt = (string) (
            Config::get('lgu.dpa.blind_index_salt')
            ?? Config::get('services.pii.blind_index_key')
            ?? Config::get('app.key')
        );

        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return hash_hmac('sha256', $normalized, $salt);
    }
}
