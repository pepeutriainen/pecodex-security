<?php
/**
 * TOTP (Google Authenticator) handler for Pecodex Security.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_TOTP {

    /**
     * Generate a new 16-character base32 secret.
     */
    public static function generate_secret( $length = 16 ) {
        $b32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $secret = "";
        for ( $i = 0; $i < $length; $i++ ) {
            $secret .= $b32[ wp_rand(0, 31) ];
        }
        return $secret;
    }

    /**
     * Get the provisioning URI for QR Code generation.
     */
    public static function get_provisioning_uri( $secret, $user_email, $issuer ) {
        $issuer = rawurlencode($issuer);
        $user_email = rawurlencode($user_email);
        return "otpauth://totp/{$issuer}:{$user_email}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a TOTP code against a secret.
     */
    public static function verify_code( $secret, $code, $discrepancy = 1 ) {
        $current_time = floor( time() / 30 );
        
        for ( $i = -$discrepancy; $i <= $discrepancy; $i++ ) {
            $calculated = self::calculate_code( $secret, $current_time + $i );
            if ( hash_equals( $calculated, (string) $code ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate a TOTP code for a specific timeslice.
     */
    private static function calculate_code( $secret, $time_slice ) {
        $secret_bytes = self::base32_decode( $secret );
        
        // Pack time into 8 bytes
        $time_bytes = pack( "N*", 0 ) . pack( "N*", $time_slice );
        
        // Generate HMAC-SHA1
        $hmac = hash_hmac( 'sha1', $time_bytes, $secret_bytes, true );
        
        // Get offset from last nibble
        $offset = ord( substr( $hmac, -1 ) ) & 0x0F;
        
        // Extract 4 bytes at offset
        $hashpart = substr( $hmac, $offset, 4 );
        
        // Unpack and mask
        $value = unpack( "N", $hashpart );
        $value = $value[1] & 0x7FFFFFFF;
        
        // Modulo 10^6
        $modulo = pow( 10, 6 );
        $code = $value % $modulo;
        
        return str_pad( $code, 6, '0', STR_PAD_LEFT );
    }

    /**
     * Decode a base32 string.
     */
    private static function base32_decode( $b32 ) {
        $b32 = strtoupper( $b32 );
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $decoded = "";
        $buffer = 0;
        $bits_left = 0;

        foreach ( str_split( $b32 ) as $char ) {
            $val = strpos( $alphabet, $char );
            if ( $val === false ) continue;
            
            $buffer <<= 5;
            $buffer |= $val;
            $bits_left += 5;
            
            if ( $bits_left >= 8 ) {
                $bits_left -= 8;
                $decoded .= chr( ($buffer >> $bits_left) & 0xFF );
            }
        }
        return $decoded;
    }
}
