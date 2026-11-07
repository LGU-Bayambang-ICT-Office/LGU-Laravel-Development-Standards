<?php

namespace App\Traits;

use Illuminate\Support\Facades\Config;

/**
 * Trait HasPiiHashing
 *
 * Implements the Blind Index Pattern for searching encrypted PII fields under RA 10173.
 *
 * Why Blind Indexing?
 * - Encrypted fields cannot be searched using SQL `WHERE column = ?` because AES-256 uses a random IV.
 * - Storing a salted HMAC-SHA256 hash in a companion column (e.g., `phone_blind_index`) allows fast,
 *   exact-match indexed lookups without exposing raw plaintext in the database.
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

        $salt = (string) (Config::get('services.pii.blind_index_key') ?? Config::get('app.key'));
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return hash_hmac('sha256', $normalized, $salt);
    }
}
