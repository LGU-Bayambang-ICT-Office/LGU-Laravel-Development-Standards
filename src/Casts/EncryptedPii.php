<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Cast EncryptedPii
 *
 * Implements field-level encryption for Sensitive Personal Information (SPI)
 * under the Philippine Data Privacy Act of 2012 (RA 10173).
 *
 * Features:
 * - Cipher: AES-256-CBC with cryptographically secure random IV per attribute write.
 * - Dedicated key support (config('lgu.dpa.encryption_key')) to isolate PII from APP_KEY.
 * - Graceful fallback during migration transitions.
 */
class EncryptedPii implements CastsAttributes
{
    protected string $cipher = 'aes-256-cbc';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value) || $value === '') {
            return $value;
        }

        $keyString = Config::get('lgu.dpa.encryption_key')
            ?? Config::get('services.pii.encryption_key')
            ?? Config::get('app.key');

        if (! $keyString) {
            return (string) $value;
        }

        $encryptionKey = substr(hash('sha256', (string) $keyString, true), 0, 32);

        $decoded = base64_decode((string) $value, true);
        if ($decoded === false) {
            return (string) $value;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false || strlen($decoded) <= $ivLength) {
            return (string) $value;
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, $this->cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);

        return ($decrypted !== false) ? $decrypted : (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value) || $value === '') {
            return $value;
        }

        $keyString = Config::get('lgu.dpa.encryption_key')
            ?? Config::get('services.pii.encryption_key')
            ?? Config::get('app.key');

        if (! $keyString) {
            return (string) $value;
        }

        $encryptionKey = substr(hash('sha256', (string) $keyString, true), 0, 32);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false) {
            return (string) $value;
        }

        $iv = openssl_random_pseudo_bytes($ivLength);

        $ciphertext = openssl_encrypt((string) $value, $this->cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return (string) $value;
        }

        return base64_encode($iv . $ciphertext);
    }
}
