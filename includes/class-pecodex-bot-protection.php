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
        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'login_form', array( $this, 'render_fields' ) );
        add_filter( 'authenticate', array( $this, 'verify_login' ), 21, 3 ); // 21 so it runs after default authentication to not block empty requests early, or maybe just 21 is fine.
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

    /**
     * Get integration settings.
     */
    private function get_integrations() {
        return get_option( 'pmc_integration_settings', array() );
    }

    /**
     * Enqueue scripts for login page.
     */
    public function enqueue_scripts() {
        $integrations = $this->get_integrations();

        // Cloudflare Turnstile
        if ( ! empty( $integrations['turnstile']['enabled'] ) && ! empty( $integrations['turnstile']['site_key'] ) ) {
            wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
        }

        // Google reCAPTCHA v3
        if ( ! empty( $integrations['recaptcha']['enabled'] ) && ! empty( $integrations['recaptcha']['site_key'] ) ) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $integrations['recaptcha']['site_key'] ), array(), null, true );
            
            $site_key = $integrations['recaptcha']['site_key'];
            wp_add_inline_script( 'google-recaptcha', "
                document.addEventListener('DOMContentLoaded', function() {
                    var form = document.getElementById('loginform');
                    if(form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            grecaptcha.ready(function() {
                                grecaptcha.execute('{$site_key}', {action: 'login'}).then(function(token) {
                                    var input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'g-recaptcha-response';
                                    input.value = token;
                                    form.appendChild(input);
                                    form.submit();
                                });
                            });
                        });
                    }
                });
            " );
        }
    }

    /**
     * Render the protection fields in the login form.
     */
    public function render_fields() {
        $integrations = $this->get_integrations();

        // Cloudflare Turnstile
        if ( ! empty( $integrations['turnstile']['enabled'] ) && ! empty( $integrations['turnstile']['site_key'] ) ) {
            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $integrations['turnstile']['site_key'] ) . '" style="margin-bottom: 15px;"></div>';
        }
    }

    /**
     * Verify tokens on login.
     */
    public function verify_login( $user, $username, $password ) {
        // If it's a GET request, just return
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return $user;
        }

        $integrations = $this->get_integrations();

        // Verify Cloudflare Turnstile
        if ( ! empty( $integrations['turnstile']['enabled'] ) && ! empty( $integrations['turnstile']['secret_key'] ) ) {
            if ( empty( $_POST['cf-turnstile-response'] ) ) {
                return new WP_Error( 'turnstile_missing', '<strong>VIRHE</strong>: Turnstile-varmennus puuttuu.' );
            }

            $verify_response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                'body' => array(
                    'secret'   => $integrations['turnstile']['secret_key'],
                    'response' => sanitize_text_field( $_POST['cf-turnstile-response'] ),
                    'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''
                )
            ) );

            if ( is_wp_error( $verify_response ) ) {
                return new WP_Error( 'turnstile_error', '<strong>VIRHE</strong>: Turnstile-varmennus epäonnistui (verkkovirhe).' );
            }

            $body = json_decode( wp_remote_retrieve_body( $verify_response ), true );
            if ( empty( $body['success'] ) ) {
                return new WP_Error( 'turnstile_failed', '<strong>VIRHE</strong>: Turnstile-varmennus hylättiin.' );
            }
        }

        // Verify Google reCAPTCHA v3
        if ( ! empty( $integrations['recaptcha']['enabled'] ) && ! empty( $integrations['recaptcha']['secret_key'] ) ) {
            if ( empty( $_POST['g-recaptcha-response'] ) ) {
                return new WP_Error( 'recaptcha_missing', '<strong>VIRHE</strong>: reCAPTCHA-varmennus puuttuu.' );
            }

            $verify_response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret'   => $integrations['recaptcha']['secret_key'],
                    'response' => sanitize_text_field( $_POST['g-recaptcha-response'] ),
                    'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''
                )
            ) );

            if ( is_wp_error( $verify_response ) ) {
                return new WP_Error( 'recaptcha_error', '<strong>VIRHE</strong>: reCAPTCHA-varmennus epäonnistui (verkkovirhe).' );
            }

            $body = json_decode( wp_remote_retrieve_body( $verify_response ), true );
            if ( empty( $body['success'] ) || $body['score'] < 0.5 ) {
                return new WP_Error( 'recaptcha_failed', '<strong>VIRHE</strong>: reCAPTCHA-varmennus hylättiin (bottiepäily).' );
            }
        }

        return $user;
    }
}

// Initialize the class
new Pecodex_Bot_Protection();
