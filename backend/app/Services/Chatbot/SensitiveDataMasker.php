<?php

namespace App\Services\Chatbot;

/**
 * Masking data sensitif sebelum disimpan ke chat_logs.
 * Mencegah penyimpanan raw data personal (email, no HP, alamat lengkap, dll).
 */
class SensitiveDataMasker
{
    /**
     * Pattern-pattern sensitif yang harus di-mask.
     */
    private const PATTERNS = [
        // Email
        '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/' => '[EMAIL_MASKED]',
        // Nomor telepon Indonesia (08xx, +62xx)
        '/(\+62|62|0)8[1-9][0-9]{6,10}/' => '[PHONE_MASKED]',
        // Token/API key patterns
        '/[0-9a-f]{32,}/' => '[TOKEN_MASKED]',
        // Latitude/Longitude precise (mask to 2 decimal places for privacy)
        '/(-?\d{1,3}\.\d{4,})/' => '[COORD_MASKED]',
    ];

    /**
     * Mask data sensitif dalam teks.
     */
    public static function mask(string $text): string
    {
        $result = $text;
        foreach (self::PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $result);
        }
        return $result;
    }

    /**
     * Mask data sensitif dalam array (recursive).
     */
    public static function maskArray(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $masked[$key] = self::mask($value);
            } elseif (is_array($value)) {
                $masked[$key] = self::maskArray($value);
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
