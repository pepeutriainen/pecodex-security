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
                        
                        if ( isset( $vuln['versions'] ) ) {
                            $versions = is_array( $vuln['versions'] ) ? $vuln['versions'] : array( $vuln['versions'] );
                            foreach ( $versions as $v_string ) {
                                if ( preg_match( '/^(<=|>=|<|>|==|=)\s*([0-9a-zA-Z\.\-]+)$/', trim( $v_string ), $matches ) ) {
                                    $op = $matches[1];
                                    $compare_ver = $matches[2];
                                    if ( version_compare( $version, $compare_ver, $op ) ) {
                                        $is_vulnerable = true;
                                        break;
                                    }
                                } else {
                                    if ( version_compare( $version, trim( $v_string ), '==' ) ) {
                                        $is_vulnerable = true;
                                        break;
                                    }
                                }
                            }
                        } elseif ( isset( $vuln['operator'] ) && is_array( $vuln['operator'] ) ) {
                            $op_data = $vuln['operator'];
                            $min_match = true;
                            $max_match = true;

                            if ( ! empty( $op_data['min_version'] ) && ! empty( $op_data['min_operator'] ) ) {
                                $op = str_replace( array( 'gt', 'ge', 'eq' ), array( '>', '>=', '==' ), $op_data['min_operator'] );
                                if ( ! version_compare( $version, $op_data['min_version'], $op ) ) {
                                    $min_match = false;
                                }
                            }
                            if ( ! empty( $op_data['max_version'] ) && ! empty( $op_data['max_operator'] ) ) {
                                $op = str_replace( array( 'lt', 'le', 'eq' ), array( '<', '<=', '==' ), $op_data['max_operator'] );
                                if ( ! version_compare( $version, $op_data['max_version'], $op ) ) {
                                    $max_match = false;
                                }
                            }
                            if ( $min_match && $max_match && ( ! empty( $op_data['min_version'] ) || ! empty( $op_data['max_version'] ) ) ) {
                                $is_vulnerable = true;
                            }
                        } else {
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
