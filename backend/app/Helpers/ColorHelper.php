<?php

declare(strict_types=1);

namespace App\Helpers;

class ColorHelper
{
    /**
     * Convert hex color to RGB values
     * 
     * @param string $hex Hex color code (e.g., '#1a56db' or '1a56db')
     * @return string RGB values as comma-separated string (e.g., '26, 86, 219')
     */
    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        
        // Handle 3-character hex codes
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "$r, $g, $b";
    }
    
    /**
     * Validate if a string is a valid hex color
     * 
     * @param string $hex
     * @return bool
     */
    public static function isValidHex(string $hex): bool
    {
        return preg_match('/^#?[0-9a-f]{3}([0-9a-f]{3})?$/i', $hex) === 1;
    }
}
