<?php

namespace Monstein\Base;

/**
 * Security Utilities
 * 
 * Provides XSS protection, input sanitization, and security helpers.
 * Supports PHP 7.4 and 8.x
 */
class SecurityUtils
{
    /**
     * Sanitize string output to prevent XSS attacks
     * 
     * @param string|null $input
     * @param int $flags htmlspecialchars flags
     * @return string
     */
    public static function escapeHtml(?string $input, int $flags = ENT_QUOTES | ENT_HTML5): string
    {
        if ($input === null) {
            return '';
        }
        
        return htmlspecialchars($input, $flags, 'UTF-8', true);
    }

    /**
     * Sanitize array of strings for output
     * 
     * @param array $data
     * @return array
     */
    public static function escapeArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $safeKey = is_string($key) ? self::escapeHtml($key) : $key;
            
            if (is_array($value)) {
                $result[$safeKey] = self::escapeArray($value);
            } elseif (is_string($value)) {
                $result[$safeKey] = self::escapeHtml($value);
            } else {
                $result[$safeKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Sanitize input to remove potential XSS vectors
     * Use for data that will be stored and later displayed
     * 
     * @param string|null $input
     * @return string
     */
    public static function sanitizeInput(?string $input): string
    {
        if ($input === null || $input === '') {
            return '';
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove invisible characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
        
        // Normalize unicode
        if (function_exists('normalizer_normalize')) {
            $normalized = normalizer_normalize($input, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $input = $normalized;
            }
        }
        
        return trim($input);
    }

    /**
     * Strip all HTML tags from input
     * 
     * @param string|null $input
     * @param string|null $allowedTags Optional allowed tags (e.g., '<p><a>')
     * @return string
     */
    public static function stripTags(?string $input, ?string $allowedTags = null): string
    {
        if ($input === null) {
            return '';
        }
        
        return strip_tags($input, $allowedTags);
    }

    /**
     * Validate and sanitize URL to prevent javascript: and data: XSS
     * 
     * @param string|null $url
     * @return string|null Returns null if URL is potentially malicious
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $url = trim($url);
        
        // Remove any null bytes or control characters
        $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url);
        
        // Decode URL entities to catch obfuscated attacks
        $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        $decoded = urldecode($decoded);
        $decoded = strtolower($decoded);
        
        // Block dangerous protocols
        $dangerousPatterns = [
            '/^javascript:/i',
            '/^vbscript:/i',
            '/^data:/i',
            '/^file:/i',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $decoded)) {
                return null;
            }
        }
        
        // Validate URL structure
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $url)) {
            // Allow relative URLs starting with /
            if (strpos($url, '/') !== 0) {
                return null;
            }
        }
        
        return $url;
    }

    /**
     * Generate a cryptographically secure random token
     * 
     * @param int $length Token length in bytes (output will be 2x in hex)
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Constant-time string comparison to prevent timing attacks
     * 
     * @param string $known The known/expected string
     * @param string $user The user-provided string
     * @return bool
     */
    public static function secureCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Validate that a string contains only alphanumeric characters
     * 
     * @param string|null $input
     * @param string $additionalChars Additional allowed characters
     * @return bool
     */
    public static function isAlphanumeric(?string $input, string $additionalChars = ''): bool
    {
        if ($input === null || $input === '') {
            return false;
        }
        
        $pattern = '/^[a-zA-Z0-9' . preg_quote($additionalChars, '/') . ']+$/';
        return (bool) preg_match($pattern, $input);
    }

    /**
     * Sanitize filename to prevent directory traversal and special characters
     * 
     * @param string|null $filename
     * @return string
     */
    public static function sanitizeFilename(?string $filename): string
    {
        if ($filename === null || $filename === '') {
            return '';
        }

        // Remove directory traversal attempts
        $filename = str_replace(['../', '..\\', '..'], '', $filename);
        
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);
        
        // Keep only safe characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');
        
        return $filename ?: 'unnamed';
    }

    /**
     * Check if request is using HTTPS
     * 
     * @param array $serverParams Server parameters from request
     * @return bool
     */
    public static function isHttps(array $serverParams): bool
    {
        // Direct HTTPS
        if (!empty($serverParams['HTTPS']) && $serverParams['HTTPS'] !== 'off') {
            return true;
        }
        
        // Behind proxy/load balancer
        if (!empty($serverParams['HTTP_X_FORWARDED_PROTO']) && 
            strtolower($serverParams['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        
        // Cloudflare
        if (!empty($serverParams['HTTP_CF_VISITOR'])) {
            $visitor = json_decode($serverParams['HTTP_CF_VISITOR'], true);
            if (isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Mask sensitive data for logging (e.g., tokens, passwords)
     * 
     * @param string $value
     * @param int $visibleChars Number of characters to show at start/end
     * @return string
     */
    public static function maskSensitive(string $value, int $visibleChars = 4): string
    {
        $length = strlen($value);
        
        if ($length <= $visibleChars * 2) {
            return str_repeat('*', $length);
        }
        
        return substr($value, 0, $visibleChars) . 
               str_repeat('*', $length - ($visibleChars * 2)) . 
               substr($value, -$visibleChars);
    }
}
