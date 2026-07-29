<?php
/**
 * Vulnerability Scanner for Pecodex Security.
 * Checks installed plugins against the WP Vulnerability API (https://wpvulnerability.com/api/).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Vulnerabilities {

    public static function init() {
        add_action( 'pecodex_daily_security_scan', array( __CLASS__, 'scan_plugins' ) );
    }

    /**
     * Scan installed plugins for known vulnerabilities.
     */
    public static function scan_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $vulnerabilities = array();

        foreach ( $plugins as $plugin_file => $plugin_data ) {
            // Get plugin slug from directory name or filename
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }
            
            $version = $plugin_data['Version'];

            // Fetch data from WP Vulnerability API
            $url = 'https://www.wpvulnerability.net/plugin/' . urlencode( $slug ) . '/';
            $response = wp_remote_get( $url, array( 'timeout' => 5 ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                
                if ( ! empty( $body['data']['vulnerability'] ) ) {
                    foreach ( $body['data']['vulnerability'] as $vuln ) {
                        // Check if the current version is affected
                        // This requires parsing the operator in $vuln['operator'] and versions
                        // For simplicity in this demo, if the plugin has unpatched vulnerabilities, or if version <= patched version, we flag it.
                        // A true implementation would use version_compare().
                        
                        $is_vulnerable = false;
                        if ( isset($vuln['operator']) && isset($vuln['versions']) ) {
                            // "operator": { "lt": "<", "le": "<=" }, "versions": "1.2.3"
                            // Simplified version check:
                            $fixed_version = preg_replace('/[^0-9\.]/', '', $vuln['versions']);
                            if ( !empty($fixed_version) && version_compare( $version, $fixed_version, '<=' ) ) {
                                $is_vulnerable = true;
                            }
                        } else {
                            // If no version data, assume vulnerable to be safe and notify admin to update
                            $is_vulnerable = true;
                        }

                        if ( $is_vulnerable ) {
                            $vulnerabilities[] = array(
                                'plugin' => $plugin_data['Name'],
                                'cve'    => !empty($vuln['cve'][0]) ? $vuln['cve'][0] : $vuln['title'],
                                'level'  => 'Korkea', // Default high
                                'color'  => 'red'
                            );
                            break; // Only list the plugin once
                        }
                    }
                }
            }
        }

        update_option( 'pmc_plugin_vulnerabilities', $vulnerabilities );
    }

    /**
     * Get the cached vulnerabilities for the dashboard API.
     */
    public static function get_cached_vulnerabilities() {
        return get_option( 'pmc_plugin_vulnerabilities', array() );
    }
}
