<?php
/**
 * Pecodex Telemetry
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pecodex_Telemetry
 *
 * Audit & Telemetry Tracker.
 */
class Pecodex_Telemetry {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'switch_theme', array( $this, 'log_switch_theme' ), 10, 3 );
		add_action( 'update_option_active_plugins', array( $this, 'log_update_option_active_plugins' ), 10, 3 );
		add_action( 'init', array( $this, 'create_live_traffic_table' ) );
		add_action( 'template_redirect', array( $this, 'log_live_traffic' ) );
	}

	/**
	 * Log theme switches.
	 *
	 * @param string   $new_name  Name of the new theme.
	 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
	 */
	public function log_switch_theme( $new_name, $new_theme, $old_theme ) {
		$this->log_action( 'theme_switched', array(
			'new_theme' => $new_name,
			'old_theme' => is_a( $old_theme, 'WP_Theme' ) ? $old_theme->get( 'Name' ) : (string) $old_theme,
		) );
	}

	/**
	 * Log plugin activations and deactivations.
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function log_update_option_active_plugins( $old_value, $value, $option ) {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$value     = is_array( $value ) ? $value : array();

		$activated   = array_diff( $value, $old_value );
		$deactivated = array_diff( $old_value, $value );

		if ( ! empty( $activated ) ) {
			$this->log_action( 'plugin_activated', array( 'plugins' => array_values( $activated ) ) );
		}

		if ( ! empty( $deactivated ) ) {
			$this->log_action( 'plugin_deactivated', array( 'plugins' => array_values( $deactivated ) ) );
		}
	}

	/**
	 * Helper function to log actions.
	 *
	 * @param string $action Action name.
	 * @param array  $data   Additional data.
	 */
	private function log_action( $action, $data = array() ) {
		$log = get_option( 'pmc_advanced_audit_log', array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$entry = array(
			'timestamp' => time(),
			'action'    => $action,
			'data'      => $data,
		);

		if ( function_exists( 'get_current_user_id' ) ) {
			$entry['user_id'] = get_current_user_id();
		}

		// Prepend the new entry.
		array_unshift( $log, $entry );

		// Limit the log to the 1000 most recent entries to avoid bloating the options table.
		$log = array_slice( $log, 0, 1000 );

		update_option( 'pmc_advanced_audit_log', $log, false ); // Pass false to prevent autoload if large.
	}

	public function create_live_traffic_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pmc_live_traffic';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(100) NOT NULL,
			url text NOT NULL,
			method varchar(10) NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			country_iso_code varchar(2) NOT NULL,
			user_agent varchar(255),
			is_proxy tinyint(1) DEFAULT 0,
			threat_score int DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function log_live_traffic() {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$ip = '';
		if ( class_exists( 'Pecodex_Firewall' ) ) {
			$ip = Pecodex_Firewall::get_client_ip();
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		if ( empty( $ip ) ) {
			return;
		}

		$transient_name = 'pmc_lt_' . md5( $ip );
		if ( get_transient( $transient_name ) ) {
			return;
		}
		set_transient( $transient_name, true, 5 );

		$country = '';
		if ( class_exists( 'Pecodex_GeoIP' ) ) {
			$loc = Pecodex_GeoIP::get_location( $ip );
			if ( is_array( $loc ) && isset( $loc['countryCode'] ) ) {
				$country = $loc['countryCode'];
			} elseif ( is_string( $loc ) ) {
				$country = $loc;
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pmc_live_traffic';
		
		$url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Unknown';
		$user_agent = substr( $user_agent, 0, 255 );

		// Check for proxy
		$is_proxy = 0;
		$proxy_headers = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_VIA', 'HTTP_X_REAL_IP' );
		foreach ( $proxy_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$is_proxy = 1;
				break;
			}
		}

		// Calculate Threat Score
		$threat_score = 0;
		if ( $is_proxy ) {
			$threat_score += 30;
		}

		$bad_ua_patterns = array( 'curl', 'python', 'wget', 'nikto', 'headless', 'phantomjs', 'bot', 'spider' );
		$ua_lower = strtolower( $user_agent );
		if ( $user_agent === 'Unknown' || empty( trim( $user_agent ) ) ) {
			$threat_score += 20;
		} else {
			foreach ( $bad_ua_patterns as $pattern ) {
				if ( strpos( $ua_lower, $pattern ) !== false ) {
					$threat_score += 30;
					break;
				}
			}
		}

		$sensitive_urls = array( 'wp-config.php', '.env', 'xmlrpc.php', '.git', 'passwd', 'eval(' );
		$url_lower = strtolower( $url );
		foreach ( $sensitive_urls as $pattern ) {
			if ( strpos( $url_lower, $pattern ) !== false ) {
				$threat_score += 40;
				break;
			}
		}
		
		if ( $threat_score > 100 ) {
			$threat_score = 100;
		}

		$wpdb->insert(
			$table_name,
			array(
				'ip' => $ip,
				'url' => $url,
				'method' => $method,
				'time' => current_time( 'mysql' ),
				'country_iso_code' => $country,
				'user_agent' => $user_agent,
				'is_proxy' => $is_proxy,
				'threat_score' => $threat_score
			)
		);
	}
}

new Pecodex_Telemetry();
