<?php
/**
 * Pecodex Security WAF Core
 *
 * Tämä luokka suoritetaan erillään WordPressistä, jotta se voi torjua pyynnöt
 * ennen ytimen ja tietokantayhteyksien latautumista.
 */

class Pecodex_WAF_Core {
    
    public static function run() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $method      = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        
        // Skip WAF for CLI
        if ( php_sapi_name() === 'cli' ) {
            return;
        }

        $payload = array_merge( $_GET, $_POST );
        
        // Laajat sääntöjoukot (Regex-pohjainen)
        $rules = self::get_rules();
        
        foreach ( $payload as $key => $value ) {
            self::check_value( $value, $rules );
        }

        // Tarkista myös evästeet
        foreach ( $_COOKIE as $key => $value ) {
            self::check_value( $value, $rules );
        }
    }

    private static function check_value( $value, $rules ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                self::check_value( $v, $rules );
            }
            return;
        }

        if ( ! is_string( $value ) ) {
            return;
        }

        foreach ( $rules as $rule_name => $pattern ) {
            if ( preg_match( $pattern, $value ) ) {
                self::block_request( $rule_name, $value );
            }
        }
    }

    private static function get_rules() {
        return array(
            'SQL_Injection_1' => '/(?i)(UNION\s+SELECT|UNION\s+ALL\s+SELECT)/',
            'SQL_Injection_2' => '/(?i)(INFORMATION_SCHEMA|CONCAT\s*\(|DROP\s+TABLE|INSERT\s+INTO)/',
            'SQL_Injection_3' => '/(?i)(\%27|\')\s*(OR|AND)\s*(1=1|\d=\d|\'a\'=\'a)/',
            'XSS_Script'      => '/(?i)(<script[^>]*>.*?<\/script>|<script[^>]*>)/s',
            'XSS_Events'      => '/(?i)(onmouseover|onerror|onload|onclick|onfocus)\s*=/s',
            'XSS_Javascript'  => '/(?i)(javascript:|vbscript:|data:text\/html)/',
            'LFI_Traversal'   => '/(\.\.\/|\.\.\\\\|\.\.\%2f|\%2e\%2e\%2f)/i',
            'LFI_Passwd'      => '/(?i)(\/etc\/passwd|\/etc\/shadow|c:\\\\windows\\\\win.ini)/',
            'RCE_Eval'        => '/(?i)(eval\s*\(|base64_decode\s*\(|system\s*\(|exec\s*\(|shell_exec\s*\()/',
            'RCE_Php'         => '/(<\?php|<\?=)/i',
        );
    }

    private static function block_request( $rule_name, $matched_value ) {
        header( 'HTTP/1.1 403 Forbidden' );
        header( 'Status: 403 Forbidden' );
        header( 'Content-Type: text/html; charset=utf-8' );
        
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'Tuntematon';

        // Logitetaan yksinkertaisesti tiedostoon, koska WP DB ei ole vielä saatavilla.
        // Myöhemmin WP:n puolelta voidaan lukea tämä loki ja siirtää tietokantaan.
        $log_file = dirname( __FILE__ ) . '/../waf.log';
        $log_entry = sprintf(
            "[%s] Blocked IP: %s | Rule: %s | URI: %s\n",
            date( 'Y-m-d H:i:s' ),
            $ip,
            $rule_name,
            isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''
        );
        @file_put_contents( $log_file, $log_entry, FILE_APPEND );

        echo "<!DOCTYPE html><html><head><title>403 Forbidden - Pecodex WAF</title></head>";
        echo "<body style='font-family: sans-serif; text-align: center; padding: 50px;'>";
        echo "<h1>Access Denied</h1>";
        echo "<p>Your request was blocked by <strong>Pecodex Security WAF</strong> because it matched a malicious signature.</p>";
        echo "<p>Reason: " . htmlspecialchars( $rule_name ) . "</p>";
        echo "<p>Your IP: " . htmlspecialchars( $ip ) . "</p>";
        echo "</body></html>";
        exit;
    }
}
