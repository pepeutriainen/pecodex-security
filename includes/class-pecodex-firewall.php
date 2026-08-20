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

		$table_traffic = $wpdb->prefix . 'pmc_traffic_log';
		$sql_traffic = "CREATE TABLE $table_traffic (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(45) NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			url text,
			method varchar(10),
			status varchar(10),
			is_bad tinyint(1) DEFAULT 0,
			country_iso_code varchar(5),
			PRIMARY KEY  (id),
			KEY ip (ip),
			KEY time (time)
		) $charset_collate;";

		$table_geoip = $wpdb->prefix . 'pmc_geoip_cache';
		$sql_geoip = "CREATE TABLE $table_geoip (
			ip varchar(45) NOT NULL,
			country_code varchar(5),
			city varchar(100),
			lat decimal(10,8),
			lng decimal(11,8),
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (ip)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_lockout );
		dbDelta( $sql_log );
		dbDelta( $sql_traffic );
		dbDelta( $sql_geoip );
	}

	public static function get_client_ip() {
		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';

		if ( ! filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
			return 'UNKNOWN';
		}

		/*
		 * Forwarded headers are client-controlled unless the immediate peer is a
		 * proxy we explicitly trust. Trusting X-Forwarded-For unconditionally
		 * lets an attacker evade per-IP bans simply by sending a forged header.
		 */
		if ( ! self::is_trusted_proxy( $remote_ip ) ) {
			return $remote_ip;
		}

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			foreach ( explode( ',', (string) $_SERVER[ $header ] ) as $candidate ) {
				$candidate = trim( $candidate );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}

		return $remote_ip;
	}

	/**
	 * Returns true only for proxy addresses configured by the site owner.
	 * Define PMC_TRUSTED_PROXY_IPS as a comma-separated list or save an array
	 * in the pmc_firewall_trusted_proxy_ips option. CIDR and wildcard rules are
	 * supported by match_ip().
	 */
	private static function is_trusted_proxy( $ip ) {
		$trusted = get_option( 'pmc_firewall_trusted_proxy_ips', array() );
		if ( defined( 'PMC_TRUSTED_PROXY_IPS' ) ) {
			$trusted = array_merge( (array) $trusted, explode( ',', PMC_TRUSTED_PROXY_IPS ) );
		}

		foreach ( (array) $trusted as $rule ) {
			if ( self::match_ip( $ip, trim( (string) $rule ) ) ) {
				return true;
			}
		}

		return false;
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
		$ip = trim( (string) $ip );
		if ( empty( $ip ) ) {
			return false;
		}

		// 1. Localhost is always allowed
		if ( in_array( $ip, array( '127.0.0.1', '::1', 'localhost' ), true ) ) {
			return true;
		}

		// 2. Logged-in administrators / staff are always allowed
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		// 3. Known Administrator & Admin Employee IPs
		$admin_ips = (array) get_option( 'pmc_admin_ips', array() );
		if ( ! empty( $admin_ips[ $ip ] ) || in_array( $ip, array_keys( $admin_ips ), true ) ) {
			return true;
		}

		// 4. Manually allowed IPs / CIDRs
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

	public static function is_admin_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( empty( $ip ) ) {
			return false;
		}
		if ( in_array( $ip, array( '127.0.0.1', '::1', 'localhost' ), true ) ) {
			return true;
		}
		$admin_ips = (array) get_option( 'pmc_admin_ips', array() );
		return ! empty( $admin_ips[ $ip ] ) || in_array( $ip, array_keys( $admin_ips ), true );
	}

	public static function match_ip( $ip, $rule ) {
		$ip   = trim( (string) $ip );
		$rule = trim( (string) $rule );
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
				$bits = (int) $bits;
				if ( $bits < 0 || $bits > 32 ) {
					return false;
				}
				$ip_long = ip2long( $ip );
				$subnet_long = ip2long( $subnet );
				$mask = 0 === $bits ? 0 : ( -1 << ( 32 - $bits ) );
				$subnet_long &= $mask;
				return ( $ip_long & $mask ) === $subnet_long;
			}
			if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$ip_bin = inet_pton( $ip );
				$subnet_bin = inet_pton( $subnet );
				if ( $ip_bin !== false && $subnet_bin !== false ) {
					$bits = (int) $bits;
					if ( $bits < 0 || $bits > 128 ) {
						return false;
					}
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
