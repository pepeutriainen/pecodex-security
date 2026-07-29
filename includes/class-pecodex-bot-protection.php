<?php
/**
 * Bot Protection Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Pecodex_Bot_Protection {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'check_bot_signatures' ) );
    }

    /**
     * Check user agent against malicious bot signatures.
     */
    public function check_bot_signatures() {
        $settings = get_option( 'pmc_firewall_settings', array() );
        if ( empty( $settings['bot_protection'] ) ) {
            return;
        }

        if ( class_exists( 'Pecodex_Firewall' ) && Pecodex_Firewall::is_ip_allowed( Pecodex_Firewall::get_client_ip() ) ) {
            return;
        }

        if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return;
        }

        $user_agent = strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

        // List of common malicious bot signatures.
        $malicious_signatures = array(
            'python-requests',
            'nikto',
            'sqlmap',
            'nmap',
            'dirb',
            'zgrab',
            'wget',
            'curl',
            'masscan',
            'zgrab2',
            'libwww-perl',
            'java',
            'scrapy',
            'datacha0s',
            'vulnscan',
            'grendel-scan',
            'hydra',
            'nuclei',
            'httpx',
            'gobuster',
            'ffuf',
            'wfuzz',
            'censys',
            'shodan',
            'ahrefsbot',
            'semrushbot',
            'mj12bot',
            'baiduspider'
        );

        foreach ( $malicious_signatures as $signature ) {
            if ( strpos( $user_agent, $signature ) !== false ) {
                wp_die( 'Bot blocked', 'Access Denied', array( 'response' => 403 ) );
            }
        }
    }
}

// Initialize the class
new Pecodex_Bot_Protection();
