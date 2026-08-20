<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Pecodex_Traffic_Logger {
	private static $log_file;

	public static function init() {
		$upload_dir = wp_upload_dir();
		self::$log_file = trailingslashit( $upload_dir['basedir'] ) . 'pecodex_traffic.log';

		// Log traffic on shutdown to avoid delaying the response
		add_action( 'shutdown', array( __CLASS__, 'log_request' ), 999 );
		
		// Register cron event for bulk processing
		if ( ! wp_next_scheduled( 'pmc_process_traffic_logs' ) ) {
			wp_schedule_event( time(), 'every_minute', 'pmc_process_traffic_logs' );
		}
		add_action( 'pmc_process_traffic_logs', array( __CLASS__, 'process_logs' ) );
	}

	public static function get_client_ip() {
		if ( class_exists( 'Pecodex_Firewall' ) && method_exists( 'Pecodex_Firewall', 'get_client_ip' ) ) {
			return Pecodex_Firewall::get_client_ip();
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'UNKNOWN';
	}

	public static function log_request() {
		if ( defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) || wp_doing_ajax() ) return;
		
		$ip = self::get_client_ip();
		if ( $ip === 'UNKNOWN' ) return;

		$raw_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$url = self::redact_request_url( $raw_url );
		
		// Skip typical static assets if they hit PHP
		if ( preg_match( '/\.(jpg|jpeg|png|gif|ico|css|js|woff|woff2)$/i', $url ) ) return;

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		
		// Attempt to guess status code
		$status = http_response_code();
		if ( ! $status ) $status = 200;

		$is_bad = self::is_suspicious_request( $raw_url, $status ) ? 1 : 0;
		
		if ( defined( 'PMC_WAF_BLOCKED' ) ) {
			$is_bad = 1;
		}

		$data = array(
			'ip'     => $ip,
			'time'   => gmdate( 'Y-m-d H:i:s' ),
			'url'    => substr( $url, 0, 500 ),
			'method' => substr( $method, 0, 10 ),
			'status' => $status,
			'is_bad' => $is_bad
		);

		@file_put_contents( self::$log_file, json_encode( $data ) . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Mark requests as suspicious only when there is evidence of an attack or a
	 * denied/failed request. A normal typo resulting in a 404 must not turn the
	 * security radar red.
	 */
	private static function is_suspicious_request( $url, $status ) {
		if ( in_array( (int) $status, array( 401, 403, 405, 408, 409, 423, 429 ), true ) || (int) $status >= 500 ) {
			return true;
		}

		$probe_pattern = '/(?:wp-config(?:\\.php)?|\\.env|\\.git|xmlrpc\\.php|phpmyadmin|vendor\\/phpunit|passwd|etc\\/shadow|eval\\(|union(?:%20|\\s)+select|<script|base64_decode)/i';
		return 404 === (int) $status && 1 === preg_match( $probe_pattern, (string) $url );
	}

	/**
	 * The radar needs a request target, not credentials, nonces, tokens or
	 * customer input. Keep the path and query parameter names only.
	 */
	private static function redact_request_url( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( ! is_array( $parts ) ) {
			return substr( (string) $url, 0, 500 );
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( empty( $parts['query'] ) ) {
			return substr( $path, 0, 500 );
		}

		$keys = array();
		foreach ( array_slice( explode( '&', $parts['query'] ), 0, 12 ) as $item ) {
			$key = sanitize_key( rawurldecode( strtok( $item, '=' ) ) );
			if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}

		return substr( $path . ( $keys ? '?' . implode( '&', $keys ) : '' ), 0, 500 );
	}

	public static function process_logs( $resolve_geoip = true ) {
		$lock_handle = @fopen( self::$log_file . '.lock', 'c' );
		if ( ! $lock_handle || ! @flock( $lock_handle, LOCK_EX | LOCK_NB ) ) {
			if ( $lock_handle ) {
				@fclose( $lock_handle );
			}
			return;
		}

		$pending_ips = get_transient( 'pmc_pending_geoip_ips' );
		$pending_ips = is_array( $pending_ips ) ? $pending_ips : array();
		$processing_file = self::$log_file . '.process';
		if ( ! file_exists( $processing_file ) ) {
			if ( ! file_exists( self::$log_file ) || ! @rename( self::$log_file, $processing_file ) ) {
				if ( $resolve_geoip && ! empty( $pending_ips ) ) {
					delete_transient( 'pmc_pending_geoip_ips' );
					self::process_geoip( $pending_ips );
				}
				return;
			}
		}

		$lines = @file( $processing_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			return; 
		}

		if ( empty( $lines ) ) {
			@unlink( $processing_file );
			if ( $resolve_geoip && ! empty( $pending_ips ) ) {
				delete_transient( 'pmc_pending_geoip_ips' );
				self::process_geoip( $pending_ips );
			}
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pmc_traffic_log';

		$values = array();
		$placeholders = array();
		$new_ips = array();

		foreach ( $lines as $line ) {
			$data = json_decode( $line, true );
			if ( ! $data ) continue;

			array_push(
				$values,
				$data['ip'],
				$data['time'],
				$data['url'],
				$data['method'],
				$data['status'],
				$data['is_bad']
			);
			$placeholders[] = "(%s, %s, %s, %s, %d, %d)";
			$new_ips[ $data['ip'] ] = true;
		}

		if ( empty( $values ) ) return;

		$query = "INSERT INTO {$table} (ip, time, url, method, status, is_bad) VALUES " . implode( ', ', $placeholders );
		$inserted = $wpdb->query( $wpdb->prepare( $query, $values ) );
		if ( false === $inserted ) {
			return;
		}

		@unlink( $processing_file );

		$new_ips = array_values( array_unique( array_merge( array_keys( $new_ips ), $pending_ips ) ) );
		if ( $resolve_geoip ) {
			delete_transient( 'pmc_pending_geoip_ips' );
			self::process_geoip( $new_ips );
		} elseif ( ! empty( $new_ips ) ) {
			set_transient( 'pmc_pending_geoip_ips', $new_ips, DAY_IN_SECONDS );
		}
		@flock( $lock_handle, LOCK_UN );
		@fclose( $lock_handle );
	}

	private static function process_geoip( $ips ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'pmc_geoip_cache';

		$placeholders = array_fill( 0, count( $ips ), '%s' );
		$in_cache = $wpdb->get_col( $wpdb->prepare( "SELECT ip FROM {$cache_table} WHERE ip IN (" . implode(',', $placeholders) . ")", $ips ) );
		
		$missing_ips = array_diff( $ips, $in_cache );
		if ( empty( $missing_ips ) ) return;

		// Resolve a small batch in cron, never in the admin page request. This
		// keeps the radar responsive and prevents fabricated fallback locations.
		foreach ( array_slice( $missing_ips, 0, 3 ) as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				continue;
			}

			$response = wp_remote_get( 'https://ipwho.is/' . rawurlencode( $ip ) . '?output=json', array( 'timeout' => 3, 'sslverify' => true ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$location = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $location ) || empty( $location['success'] ) || ! isset( $location['latitude'], $location['longitude'] ) ) {
				continue;
			}

			$wpdb->replace(
				$cache_table,
				array(
					'ip'           => $ip,
					'country_code' => isset( $location['country_code'] ) ? $location['country_code'] : '',
					'city'         => isset( $location['city'] ) ? $location['city'] : '',
					'lat'          => (float) $location['latitude'],
					'lng'          => (float) $location['longitude'],
					'updated'      => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%s', '%f', '%f', '%s' )
			);
		}
	}
}
