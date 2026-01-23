<?php
/**
 * Simple encryption utility using OpenSSL
 * 
 * AES-256-GCM by default. No custom crypto, no fancy schemes.
 * Just OpenSSL doing what it does best.
 * 
 * Usage:
 *   $crypt = new Encryption('your-secret-key-at-least-32-chars');
 *   $encrypted = $crypt->encrypt('sensitive data');
 *   $decrypted = $crypt->decrypt($encrypted);
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

class Encryption
{
    /** @var string */
    private $key;

    /** @var string */
    private $cipher;

    /** @var int */
    private $tagLength = 16;

    /**
     * @param string $key    Encryption key (will be hashed to 32 bytes)
     * @param string $cipher OpenSSL cipher (default: AES-256-GCM)
     */
    public function __construct($key, $cipher = 'aes-256-gcm')
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Encryption key cannot be empty');
        }

        // Derive a 32-byte key from the provided key
        $this->key = hash('sha256', $key, true);
        $this->cipher = $cipher;
    }

    /**
     * Encrypt data
     * 
     * @param string $plaintext
     * @return string Base64-encoded encrypted data
     * @throws \RuntimeException
     */
    public function encrypt($plaintext)
    {
        if (!is_string($plaintext)) {
            $plaintext = serialize($plaintext);
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);

        if ($this->isGcm()) {
            $tag = '';
            $encrypted = openssl_encrypt(
                $plaintext,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                $this->tagLength
            );

            if ($encrypted === false) {
                throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
            }

            // Format: iv + tag + encrypted
            return base64_encode($iv . $tag . $encrypted);
        }

        // Non-GCM cipher (e.g., AES-256-CBC)
        $encrypted = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Add HMAC for authentication
        $data = $iv . $encrypted;
        $hmac = hash_hmac('sha256', $data, $this->key, true);

        return base64_encode($hmac . $data);
    }

    /**
     * Decrypt data
     * 
     * @param string $encrypted Base64-encoded encrypted data
     * @return string|false
     * @throws \RuntimeException
     */
    public function decrypt($encrypted)
    {
        $data = base64_decode($encrypted, true);
        
        if ($data === false) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if ($this->isGcm()) {
            // Format: iv (12) + tag (16) + encrypted
            if (strlen($data) < $ivLength + $this->tagLength) {
                return false;
            }

            $iv = substr($data, 0, $ivLength);
            $tag = substr($data, $ivLength, $this->tagLength);
            $ciphertext = substr($data, $ivLength + $this->tagLength);

            $decrypted = openssl_decrypt(
                $ciphertext,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $decrypted;
        }

        // Non-GCM cipher with HMAC
        if (strlen($data) < 32 + $ivLength) {
            return false;
        }

        $hmac = substr($data, 0, 32);
        $iv = substr($data, 32, $ivLength);
        $ciphertext = substr($data, 32 + $ivLength);

        // Verify HMAC
        $expectedHmac = hash_hmac('sha256', $iv . $ciphertext, $this->key, true);
        if (!hash_equals($expectedHmac, $hmac)) {
            return false;
        }

        return openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Encrypt an array (serializes automatically)
     * 
     * @param array $data
     * @return string
     */
    public function encryptArray(array $data)
    {
        return $this->encrypt(json_encode($data));
    }

    /**
     * Decrypt to array
     * 
     * @param string $encrypted
     * @return array|false
     */
    public function decryptArray($encrypted)
    {
        $decrypted = $this->decrypt($encrypted);
        
        if ($decrypted === false) {
            return false;
        }

        $data = json_decode($decrypted, true);
        
        return json_last_error() === JSON_ERROR_NONE ? $data : false;
    }

    /**
     * Generate a secure random key
     * 
     * @param int $length
     * @return string
     */
    public static function generateKey($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash a value (one-way)
     * 
     * @param string $value
     * @param string $algo
     * @return string
     */
    public static function hash($value, $algo = 'sha256')
    {
        return hash($algo, $value);
    }

    /**
     * Create a signed token (HMAC)
     * 
     * @param string $data
     * @return string
     */
    public function sign($data)
    {
        $signature = hash_hmac('sha256', $data, $this->key);
        return base64_encode($data . '.' . $signature);
    }

    /**
     * Verify and return signed data
     * 
     * @param string $token
     * @return string|false
     */
    public function verify($token)
    {
        $decoded = base64_decode($token, true);
        
        if ($decoded === false) {
            return false;
        }

        $parts = explode('.', $decoded);
        
        if (count($parts) !== 2) {
            return false;
        }

        list($data, $signature) = $parts;
        $expectedSignature = hash_hmac('sha256', $data, $this->key);

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        return $data;
    }

    /**
     * Check if current cipher is GCM mode
     * 
     * @return bool
     */
    private function isGcm()
    {
        return stripos($this->cipher, 'gcm') !== false;
    }
}
