<?php
/**
 * String manipulation utilities
 * 
 * Static helper methods for common string operations.
 * No dependencies, no state, just functions.
 * 
 * Usage:
 *   Str::slug('Hello World');   // 'hello-world'
 *   Str::truncate($text, 100);  // 'text...'
 *   Str::random(16);            // 'a1b2c3d4e5f6g7h8'
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

class Str
{
    /**
     * Generate a URL-friendly slug
     * 
     * @param string $text
     * @param string $separator
     * @return string
     */
    public static function slug($text, $separator = '-')
    {
        // Convert to ASCII if possible
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        }

        // Lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric with separator
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);

        // Remove duplicate separators
        $text = preg_replace('/' . preg_quote($separator) . '+/', $separator, $text);

        // Trim separators from ends
        return trim($text, $separator);
    }

    /**
     * Truncate string to length
     * 
     * @param string $text
     * @param int    $length
     * @param string $suffix
     * @return string
     */
    public static function truncate($text, $length, $suffix = '...')
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Generate random string
     * 
     * @param int    $length
     * @param string $chars  Character set to use
     * @return string
     */
    public static function random($length = 16, $chars = null)
    {
        if ($chars === null) {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        }

        $result = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Check if string starts with substring
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Check if string ends with substring
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Check if string contains substring
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * Convert to camelCase
     * 
     * @param string $text
     * @return string
     */
    public static function camel($text)
    {
        $text = ucwords(str_replace(['-', '_'], ' ', $text));
        return lcfirst(str_replace(' ', '', $text));
    }

    /**
     * Convert to StudlyCase (PascalCase)
     * 
     * @param string $text
     * @return string
     */
    public static function studly($text)
    {
        $text = ucwords(str_replace(['-', '_'], ' ', $text));
        return str_replace(' ', '', $text);
    }

    /**
     * Convert to snake_case
     * 
     * @param string $text
     * @return string
     */
    public static function snake($text)
    {
        // Insert underscore before uppercase letters
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);
        
        // Replace spaces and hyphens with underscores
        $text = str_replace([' ', '-'], '_', $text);

        return strtolower($text);
    }

    /**
     * Convert to kebab-case
     * 
     * @param string $text
     * @return string
     */
    public static function kebab($text)
    {
        return str_replace('_', '-', self::snake($text));
    }

    /**
     * Convert to Title Case
     * 
     * @param string $text
     * @return string
     */
    public static function title($text)
    {
        return ucwords(strtolower($text));
    }

    /**
     * Limit string by word count
     * 
     * @param string $text
     * @param int    $words
     * @param string $suffix
     * @return string
     */
    public static function words($text, $words, $suffix = '...')
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $text, $matches);

        if (!isset($matches[0]) || mb_strlen($text) === mb_strlen($matches[0])) {
            return $text;
        }

        return rtrim($matches[0]) . $suffix;
    }

    /**
     * Mask sensitive string
     * 
     * @param string $text
     * @param int    $visible Number of visible chars at start and end
     * @param string $mask
     * @return string
     */
    public static function mask($text, $visible = 4, $mask = '*')
    {
        $length = strlen($text);

        if ($length <= $visible * 2) {
            return str_repeat($mask, $length);
        }

        $start = substr($text, 0, $visible);
        $end = substr($text, -$visible);
        $middle = str_repeat($mask, $length - ($visible * 2));

        return $start . $middle . $end;
    }

    /**
     * Check if string is valid email
     * 
     * @param string $email
     * @return bool
     */
    public static function isEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if string is valid URL
     * 
     * @param string $url
     * @return bool
     */
    public static function isUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if string is valid JSON
     * 
     * @param string $json
     * @return bool
     */
    public static function isJson($json)
    {
        if (!is_string($json)) {
            return false;
        }
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Extract text between delimiters
     * 
     * @param string $text
     * @param string $start
     * @param string $end
     * @return string|null
     */
    public static function between($text, $start, $end)
    {
        $startPos = strpos($text, $start);
        if ($startPos === false) {
            return null;
        }

        $startPos += strlen($start);
        $endPos = strpos($text, $end, $startPos);

        if ($endPos === false) {
            return null;
        }

        return substr($text, $startPos, $endPos - $startPos);
    }

    /**
     * Replace first occurrence
     * 
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function replaceFirst($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);

        if ($pos !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace last occurrence
     * 
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function replaceLast($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    /**
     * Pad string on both sides
     * 
     * @param string $text
     * @param int    $length
     * @param string $pad
     * @return string
     */
    public static function padBoth($text, $length, $pad = ' ')
    {
        return str_pad($text, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Generate UUID v4
     * 
     * @return string
     */
    public static function uuid()
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Ordinalize number (1st, 2nd, 3rd, etc.)
     * 
     * @param int $number
     * @return string
     */
    public static function ordinal($number)
    {
        $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return $number . 'th';
        }

        return $number . $suffix[$number % 10];
    }
}
