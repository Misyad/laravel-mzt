<?php

namespace App\Support;

/**
 * Masking helpers for the public "Cek Status KTA" endpoints.
 *
 * The public contract must never reveal a member's full identity before
 * ownership is verified. These helpers reduce a name / member number to a
 * shape that lets the owner recognise themselves while remaining useless for
 * enumeration.
 */
class KtaMasker
{
    /**
     * Mask a person name: keep the first letter of each word, collapse the
     * rest. Multi-word names keep their word count so the owner can sanity
     * check the shape.
     *
     * "Achmad Hasanudin" -> "A*** H***"
     * "Achmad"           -> "A***"
     */
    public static function name(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $name) ?: [];

        $masked = array_map(static function (string $word): string {
            $first = mb_substr($word, 0, 1, 'UTF-8');
            return $first . '***';
        }, $words);

        return implode(' ', $masked);
    }

    /**
     * Mask a member number: keep the last 3 characters so the owner can
     * confirm it without exposing the full identifier.
     *
     * "0174011119" -> "MZT***119"
     * "123"        -> "MZT***123"
     */
    public static function memberId(?string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }

        $tail = mb_substr($id, -3, null, 'UTF-8');

        return 'MZT***' . $tail;
    }

    /**
     * Normalise a member number for exact comparison (trim only — the
     * identifier is already canonical, no case folding needed).
     */
    public static function normalizeMemberId(?string $id): string
    {
        return trim((string) $id);
    }

    /**
     * Normalise a person name for exact, case-insensitive comparison:
     * lowercase, collapse internal whitespace, trim. Deliberately does NOT
     * transliterate or strip diacritics so input must match the stored form.
     */
    public static function normalizeName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Extract the last 4 digits of a phone number, ignoring non-digits.
     * Returns null when fewer than 4 digits are present.
     */
    public static function phoneLast4(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }
}
