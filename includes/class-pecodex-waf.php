<?php
/**
 * Pecodex WAF (Web Application Firewall)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Pecodex_WAF {

    /**
     * Initialize the WAF.
     */
    public static function init() {
        // Hook into 'init' very early (priority 1) to intercept requests before processing.
        add_action( 'init', array( __CLASS__, 'check_request' ), 1 );
    }

    /**
     * Check incoming requests for basic SQLi and XSS signatures.
     */
    public static function check_request() {
        if ( class_exists( 'Pecodex_Firewall' ) && Pecodex_Firewall::is_ip_allowed( Pecodex_Firewall::get_client_ip() ) ) {
            return;
        }

        $signatures = array(
            '<script>',
            '</script>',
            'UNION SELECT',
            'base64_decode',
            'eval(',
            'document.cookie',
            'CONCAT(',
            'DROP TABLE',
            // Path Traversal / LFI
            '../',
            '..\\',
            'etc/passwd',
            // Advanced XSS
            'javascript:',
            'onerror=',
            'onload=',
            // SQLi
            'INFORMATION_SCHEMA',
            '--',
            '/*',
            '*/',
            'OR 1=1',
            'AND 1=1'
        );

        $request_data = array_merge( $_GET, $_POST );
        self::scan_array( $request_data, $signatures );
    }

    /**
     * Recursively scan an array for malicious signatures.
     *
     * @param array $data       The data to scan.
     * @param array $signatures The malicious signatures to look for.
     */
    private static function scan_array( $data, $signatures ) {
        if ( ! is_array( $data ) ) {
            return;
        }

        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                self::scan_array( $value, $signatures );
            } elseif ( is_string( $value ) ) {
                foreach ( $signatures as $signature ) {
                    if ( stripos( $value, $signature ) !== false ) {
                        global $wpdb;
                        $wpdb->insert(
                            $wpdb->prefix . 'pmc_lockout_log',
                            array(
                                'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                                'reason'     => 'WAF Block: ' . $signature,
                                'time'       => current_time( 'mysql' )
                            )
                        );
                        
                        if ( class_exists('Pecodex_Notifications') ) {
                            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'Tuntematon';
                            $subject = 'Palomuurin lukitus havaittu';
                            $message = sprintf("WAF on lukinnut haitallisen pyynnön.\n\nIP-osoite: %s\nSyy: %s\nURL: %s", $ip, $signature, $_SERVER['REQUEST_URI'] ?? 'Tuntematon');
                            Pecodex_Notifications::send_notification('firewall', $subject, $message);
                        }
                        
                        wp_die( 'WAF Blocked', 'Access Denied', array( 'response' => 403 ) );
                    }
                }
            }
        }
    }
}

Pecodex_WAF::init();
