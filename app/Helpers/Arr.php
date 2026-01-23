<?php
/**
 * Array manipulation utilities
 * 
 * Static helper methods for common array operations.
 * Supports dot notation for nested access.
 * 
 * Usage:
 *   Arr::get($array, 'user.name', 'default');
 *   Arr::pluck($users, 'email');
 *   Arr::only($data, ['id', 'name']);
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

class Arr
{
    /**
     * Get value using dot notation
     * 
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(array $array, $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set value using dot notation
     * 
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     * @return array
     */
    public static function set(array &$array, $key, $value)
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Check if key exists using dot notation
     * 
     * @param array  $array
     * @param string $key
     * @return bool
     */
    public static function has(array $array, $key)
    {
        if (isset($array[$key])) {
            return true;
        }

        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Remove key using dot notation
     * 
     * @param array  $array
     * @param string $key
     * @return array
     */
    public static function forget(array &$array, $key)
    {
        $keys = explode('.', $key);
        $current = &$array;

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return $array;
            }

            $current = &$current[$segment];
        }

        unset($current[array_shift($keys)]);

        return $array;
    }

    /**
     * Get only specified keys
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function only(array $array, array $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Get all except specified keys
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function except(array $array, array $keys)
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Pluck values from nested arrays
     * 
     * @param array       $array
     * @param string      $value
     * @param string|null $key
     * @return array
     */
    public static function pluck(array $array, $value, $key = null)
    {
        $results = [];

        foreach ($array as $item) {
            $itemValue = is_object($item) 
                ? (isset($item->$value) ? $item->$value : null)
                : (isset($item[$value]) ? $item[$value] : null);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) 
                    ? (isset($item->$key) ? $item->$key : null)
                    : (isset($item[$key]) ? $item[$key] : null);
                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Get first element
     * 
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function first(array $array, callable $callback = null, $default = null)
    {
        if ($callback === null) {
            if (empty($array)) {
                return $default;
            }
            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get last element
     * 
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function last(array $array, callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Flatten multi-dimensional array
     * 
     * @param array $array
     * @param int   $depth
     * @return array
     */
    public static function flatten(array $array, $depth = INF)
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, self::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /**
     * Group array by key
     * 
     * @param array  $array
     * @param string $key
     * @return array
     */
    public static function groupBy(array $array, $key)
    {
        $result = [];

        foreach ($array as $item) {
            $groupKey = is_object($item) 
                ? (isset($item->$key) ? $item->$key : null)
                : (isset($item[$key]) ? $item[$key] : null);

            $result[$groupKey][] = $item;
        }

        return $result;
    }

    /**
     * Key array by field
     * 
     * @param array  $array
     * @param string $key
     * @return array
     */
    public static function keyBy(array $array, $key)
    {
        $result = [];

        foreach ($array as $item) {
            $itemKey = is_object($item) 
                ? (isset($item->$key) ? $item->$key : null)
                : (isset($item[$key]) ? $item[$key] : null);

            $result[$itemKey] = $item;
        }

        return $result;
    }

    /**
     * Filter array where key/value matches
     * 
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     * @return array
     */
    public static function where(array $array, $key, $value)
    {
        return array_filter($array, function ($item) use ($key, $value) {
            $itemValue = is_object($item) 
                ? (isset($item->$key) ? $item->$key : null)
                : (isset($item[$key]) ? $item[$key] : null);

            return $itemValue === $value;
        });
    }

    /**
     * Sort array by key
     * 
     * @param array  $array
     * @param string $key
     * @param string $direction
     * @return array
     */
    public static function sortBy(array $array, $key, $direction = 'asc')
    {
        usort($array, function ($a, $b) use ($key, $direction) {
            $aValue = is_object($a) ? $a->$key : $a[$key];
            $bValue = is_object($b) ? $b->$key : $b[$key];

            $result = $aValue <=> $bValue;

            return strtolower($direction) === 'desc' ? -$result : $result;
        });

        return $array;
    }

    /**
     * Get random element(s)
     * 
     * @param array    $array
     * @param int|null $count
     * @return mixed
     */
    public static function random(array $array, $count = null)
    {
        if ($count === null) {
            return $array[array_rand($array)];
        }

        if ($count > count($array)) {
            $count = count($array);
        }

        $keys = array_rand($array, $count);
        $keys = is_array($keys) ? $keys : [$keys];

        return array_values(array_intersect_key($array, array_flip($keys)));
    }

    /**
     * Shuffle array
     * 
     * @param array $array
     * @return array
     */
    public static function shuffle(array $array)
    {
        shuffle($array);
        return $array;
    }

    /**
     * Wrap value in array if not already
     * 
     * @param mixed $value
     * @return array
     */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Check if array is associative
     * 
     * @param array $array
     * @return bool
     */
    public static function isAssoc(array $array)
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Chunk array into smaller arrays
     * 
     * @param array $array
     * @param int   $size
     * @return array
     */
    public static function chunk(array $array, $size)
    {
        return array_chunk($array, $size);
    }

    /**
     * Collapse array of arrays into single array
     * 
     * @param array $array
     * @return array
     */
    public static function collapse(array $array)
    {
        $results = [];

        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Cross join arrays
     * 
     * @param array ...$arrays
     * @return array
     */
    public static function crossJoin(...$arrays)
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }

    /**
     * Unique array values (maintains keys)
     * 
     * @param array $array
     * @return array
     */
    public static function unique(array $array)
    {
        return array_unique($array, SORT_REGULAR);
    }

    /**
     * Sum values in array
     * 
     * @param array       $array
     * @param string|null $key
     * @return int|float
     */
    public static function sum(array $array, $key = null)
    {
        if ($key === null) {
            return array_sum($array);
        }

        return array_sum(self::pluck($array, $key));
    }

    /**
     * Average values in array
     * 
     * @param array       $array
     * @param string|null $key
     * @return float
     */
    public static function avg(array $array, $key = null)
    {
        $count = count($array);
        
        if ($count === 0) {
            return 0;
        }

        return self::sum($array, $key) / $count;
    }
}
