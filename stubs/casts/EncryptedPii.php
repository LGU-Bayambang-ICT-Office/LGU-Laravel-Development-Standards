<?php
namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast EncryptedPii
 *
 * Implements field-level encryption for Sensitive Personal Information (SPI)
 * under the Philippine Data Privacy Act of 2012 (RA 10173).
 *
 * Features:
 * - Cipher: AES-256-CBC with cryptographically secure random IV per attribute write.
 * - Dedicated key support (config('services.pii.encryption_key')) to isolate PII from APP_KEY.
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

        $keyString = config('services.pii.encryption_key') ?? config('app.key');
        if (! $keyString) {
            return $value;
        }

        $encryptionKey = substr(hash('sha256', (string) $keyString, true), 0, 32);

        $decoded = base64_decode((string) $value, true);
        if ($decoded === false) {
            return $value;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if (strlen($decoded) <= $ivLength) {
            return $value;
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, $this->cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);

        return ($decrypted !== false) ? $decrypted : $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value) || $value === '') {
            return $value;
        }

        $keyString = config('services.pii.encryption_key') ?? config('app.key');
        if (! $keyString) {
            return $value;
        }

        $encryptionKey = substr(hash('sha256', (string) $keyString, true), 0, 32);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $ciphertext = openssl_encrypt((string) $value, $this->cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return $value;
        }

        return base64_encode($iv . $ciphertext);
    }
}
