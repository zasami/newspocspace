<?php
/**
 * Input sanitization
 */
class Sanitize
{
    public static function html(?string $val): string
    {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function email(?string $val): string
    {
        $val = trim($val ?? '');
        return filter_var($val, FILTER_VALIDATE_EMAIL) ? strtolower($val) : '';
    }

    public static function phone(?string $val): string
    {
        $val = preg_replace('/[^\d+\s]/', '', $val ?? '');
        return trim($val);
    }

    public static function int($val): int
    {
        return (int) $val;
    }

    public static function float($val): float
    {
        return (float) $val;
    }

    public static function text(?string $val, int $maxLen = 1000): string
    {
        $val = trim($val ?? '');
        return mb_substr($val, 0, $maxLen);
    }

    public static function date(?string $val): string
    {
        $val = trim($val ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }
        return '';
    }

    public static function time(?string $val): string
    {
        $val = trim($val ?? '');
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $val)) {
            return $val;
        }
        return '';
    }
}
