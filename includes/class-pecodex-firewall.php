<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Pecodex_Firewall {

	public function __construct() {
		add_action( 'init', array( $this, 'check_lockout' ) );
		add_action( 'wp_login_failed', array( $this, 'handle_login_failed' ) );
		add_action( 'template_redirect', array( $this, 'handle_404' ) );
	}

	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_lockout = $wpdb->prefix . 'pmc_lockout';
		$sql_lockout = "CREATE TABLE $table_lockout (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(45) NOT NULL,
			status varchar(20) NOT NULL,
			release_time bigint(20) NOT NULL,
			PRIMARY KEY  (id),
			KEY ip (ip)
		) $charset_collate;";

		$table_log = $wpdb->prefix . 'pmc_lockout_log';
		$sql_log = "CREATE TABLE $table_log (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(45) NOT NULL,
			type varchar(50) NOT NULL,
			date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			country_iso_code varchar(5),
			PRIMARY KEY  (id),
			KEY ip (ip)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_lockout );
		dbDelta( $sql_log );
	}

	public static function get_client_ip() {
		$ipaddress = '';
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} else if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		} else {
			$ipaddress = 'UNKNOWN';
		}
		
		// If multiple IPs are present, use the first one
        if (strpos($ipaddress, ',') !== false) {
            $ipaddress = explode(',', $ipaddress)[0];
        }
        
		return trim($ipaddress);
	}

	public function check_lockout() {
		global $wpdb;
		$ip = self::get_client_ip();
		$table_lockout = $wpdb->prefix . 'pmc_lockout';
		$time = time();

		// Prevent checking during activation to avoid database deadlocks
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'activate' ) {
			return;
		}

		if ( self::is_ip_allowed( $ip ) ) {
			return;
		}

		// Check manual bans (CIDR/Wildcard support)
		$manual_bans = get_option( 'pmc_firewall_banned_ips', array() );
		if ( is_array( $manual_bans ) ) {
			foreach ( $manual_bans as $banned_rule ) {
				if ( self::match_ip( $ip, $banned_rule ) ) {
					wp_die( 'Blocked', 'Access Denied', array( 'response' => 403 ) );
				}
			}
		}

		$lockout = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_lockout WHERE ip = %s AND status = 'blocked' AND release_time > %d",
			$ip, $time
		) );

		if ( $lockout ) {
			wp_die( 'Blocked', 'Access Denied', array( 'response' => 403 ) );
		}
	}

	public function handle_login_failed( $username ) {
		$settings = get_option( 'pmc_firewall_settings', array() );
		$login_enabled = isset( $settings['login']['enabled'] ) ? $settings['login']['enabled'] : false;
		$login_attempts = isset( $settings['login']['attempt'] ) ? (int) $settings['login']['attempt'] : 5;

		if ( ! $login_enabled ) {
			return;
		}

		$ip = self::get_client_ip();
		
		if ( self::is_ip_allowed( $ip ) ) {
			return;
		}
		
		$this->log_event( $ip, 'auth_fail' );
		$this->check_and_block( $ip, 'auth_fail', $login_attempts, 'auth_lock' );
	}

	public function handle_404() {
		if ( ! is_404() ) {
			return;
		}

		$settings = get_option( 'pmc_firewall_settings', array() );
		$notfound_enabled = isset( $settings['notfound']['enabled'] ) ? $settings['notfound']['enabled'] : false;
		$notfound_attempts = isset( $settings['notfound']['attempt'] ) ? (int) $settings['notfound']['attempt'] : 20;

		if ( ! $notfound_enabled ) {
			return;
		}

		$ip = self::get_client_ip();
		
		if ( self::is_ip_allowed( $ip ) ) {
			return;
		}
		
		$this->log_event( $ip, '404_fail' );
		$this->check_and_block( $ip, '404_fail', $notfound_attempts, '404_lock' );
	}

	private function log_event( $ip, $type ) {
		global $wpdb;
		$table_log = $wpdb->prefix . 'pmc_lockout_log';
		$wpdb->insert(
			$table_log,
			array(
				'ip'   => $ip,
				'type' => $type,
				'date' => current_time( 'mysql' ),
			),
			array(
				'%s',
				'%s',
				'%s'
			)
		);
	}

	public static function is_ip_allowed( $ip ) {
		$allowed = get_option( 'pmc_firewall_allowed_ips', array() );
		if ( is_array( $allowed ) ) {
			foreach ( $allowed as $rule ) {
				if ( self::match_ip( $ip, $rule ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function match_ip( $ip, $rule ) {
		if ( empty( $ip ) || empty( $rule ) ) {
			return false;
		}

		// Exact match
		if ( $ip === $rule ) {
			return true;
		}

		// Wildcard match
		if ( strpos( $rule, '*' ) !== false ) {
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $rule, '/' ) ) . '$/';
			if ( preg_match( $regex, $ip ) ) {
				return true;
			}
		}

		// CIDR match
		if ( strpos( $rule, '/' ) !== false ) {
			list( $subnet, $bits ) = explode( '/', $rule, 2 );
			if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ip_long = ip2long( $ip );
				$subnet_long = ip2long( $subnet );
				$mask = -1 << ( 32 - (int) $bits );
				$subnet_long &= $mask;
				return ( $ip_long & $mask ) === $subnet_long;
			}
			if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$ip_bin = inet_pton( $ip );
				$subnet_bin = inet_pton( $subnet );
				if ( $ip_bin !== false && $subnet_bin !== false ) {
					$bits = (int) $bits;
					$bytes = (int) ( $bits / 8 );
					if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
						return false;
					}
					$remainder = $bits % 8;
					if ( $remainder > 0 ) {
						$mask = (int) ( 0xff << ( 8 - $remainder ) );
						return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask );
					}
					return true;
				}
			}
		}

		return false;
	}

	private function check_and_block( $ip, $fail_type, $max_attempts, $lock_type ) {
		global $wpdb;
		$table_log = $wpdb->prefix . 'pmc_lockout_log';
		$table_lockout = $wpdb->prefix . 'pmc_lockout';

		$time_15_mins_ago = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 15 * 60 );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_log WHERE ip = %s AND type = %s AND date > %s",
			$ip, $fail_type, $time_15_mins_ago
		) );

		if ( $count > $max_attempts ) {
			$settings = get_option( 'pmc_firewall_settings', array() );
			$lockout_duration = isset( $settings['login']['lockout_duration'] ) ? (int) $settings['login']['lockout_duration'] : ( 4 * 60 * 60 );
			
			$release_time = time() + $lockout_duration;
			
			// Check if already locked
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_lockout WHERE ip = %s AND status = 'blocked'",
				$ip
			) );

			if ( $existing ) {
				$wpdb->update(
					$table_lockout,
					array( 'release_time' => $release_time ),
					array( 'id' => $existing ),
					array( '%d' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$table_lockout,
					array(
						'ip'           => $ip,
						'status'       => 'blocked',
						'release_time' => $release_time
					),
					array(
						'%s',
						'%s',
						'%d'
					)
				);
				$this->log_event( $ip, $lock_type );
			}
		}
	}
}

