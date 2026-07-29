<?php
/**
 * Rate Limiting Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Rate_Limit {

    /**
     * Initialize the rate limiter
     */
    public static function init() {
        // Hook early into init at priority 1
        add_action( 'init', array( __CLASS__, 'check_rate_limit' ), 1 );
        
        // Brute Force suojaus
        add_action( 'wp_login_failed', array( __CLASS__, 'handle_failed_login' ) );
        add_action( 'wp_authenticate', array( __CLASS__, 'check_login_lockout' ), 10, 1 );
    }

    /**
     * Check the rate limit for the current IP
     */
    public static function check_rate_limit() {
        $ip = self::get_client_ip();
        
        if ( empty( $ip ) ) {
            return;
        }

        if ( class_exists( 'Pecodex_Firewall' ) && Pecodex_Firewall::is_ip_allowed( $ip ) ) {
            return;
        }

        $transient_name = 'pecodex_rl_' . md5( $ip );
        
        // Use APCu if available for better performance, otherwise fallback to WP transients
        if ( function_exists( 'apcu_fetch' ) ) {
            $count = apcu_fetch( $transient_name );
            if ( $count === false ) {
                apcu_store( $transient_name, 1, 10 );
            } else {
                $count++;
                if ( $count > 60 ) {
                    self::block_request();
                } else {
                    apcu_store( $transient_name, $count, 10 );
                }
            }
        } else {
            self::handle_transient_fallback( $transient_name );
        }
    }

    /**
     * Fallback to WordPress transients for rate limiting
     */
    private static function handle_transient_fallback( $transient_name ) {
        $data = get_transient( $transient_name );
        $current_time = time();
        
        if ( false === $data || ! is_array( $data ) || $current_time > $data['expires'] ) {
            // First request, or expired, start counter
            $data = array(
                'count' => 1,
                'expires' => $current_time + 10,
            );
            set_transient( $transient_name, $data, 10 );
        } else {
            $data['count']++;
            if ( $data['count'] > 60 ) {
                self::block_request();
            } else {
                // Keep the original expiration window
                $remaining_time = max( 1, $data['expires'] - $current_time );
                set_transient( $transient_name, $data, $remaining_time );
            }
        }
    }

    /**
     * Get the client IP safely
     */
    private static function get_client_ip() {
        // If Pecodex_Firewall has a method, use it
        if ( class_exists( 'Pecodex_Firewall' ) && method_exists( 'Pecodex_Firewall', 'get_client_ip' ) ) {
            return Pecodex_Firewall::get_client_ip();
        }

        // Otherwise use a safe IP check fallback
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) ) {
                // Handle multiple IPs in the header (e.g., X-Forwarded-For: client, proxy1, proxy2)
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Block the request with a 429 status
     */
    private static function block_request() {
        wp_die( 
            esc_html__( 'Too Many Requests. Please try again later.', 'pecodex-media-control' ), 
            esc_html__( '429 Too Many Requests', 'pecodex-media-control' ), 
            array( 'response' => 429 ) 
        );
    }

    /**
     * Handle failed login attempt
     */
    public static function handle_failed_login( $username ) {
        $ip = self::get_client_ip();
        if ( empty( $ip ) ) return;

        $transient_name = 'pecodex_bf_' . md5( $ip );
        $failed_attempts = (int) get_transient( $transient_name );
        $failed_attempts++;

        // Lockout for 15 minutes after 5 failed attempts
        if ( $failed_attempts >= 5 ) {
            set_transient( 'pecodex_lockout_' . md5( $ip ), time() + ( 15 * MINUTE_IN_SECONDS ), 15 * MINUTE_IN_SECONDS );
            
            global $wpdb;
            $table = $wpdb->prefix . 'pmc_lockout_log';
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
                $wpdb->insert( $table, array(
                    'ip' => $ip,
                    'type' => 'auth_lock',
                    'reason' => 'Brute Force Lockout (5 failed attempts)',
                    'date' => current_time( 'mysql' )
                ) );
            }
        } else {
            set_transient( $transient_name, $failed_attempts, 15 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * Check if IP is locked out from logging in
     */
    public static function check_login_lockout( $username ) {
        $ip = self::get_client_ip();
        if ( empty( $ip ) ) return;

        $lockout_transient = 'pecodex_lockout_' . md5( $ip );
        if ( get_transient( $lockout_transient ) ) {
            wp_die(
                esc_html__( 'Olet yrittänyt kirjautua sisään liian monta kertaa. IP-osoitteesi on estetty tilapäisesti.', 'pecodex-media-control' ),
                esc_html__( 'Kirjautuminen estetty', 'pecodex-media-control' ),
                array( 'response' => 403 )
            );
        }
    }
}

// Initialize the class
Pecodex_Rate_Limit::init();
