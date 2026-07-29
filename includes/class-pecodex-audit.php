<?php
/**
 * Audit & Notifications Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_Audit {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'create_table' ) );
		add_action( 'activated_plugin', array( $this, 'log_plugin_activation' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivation' ), 10, 2 );
		add_action( 'profile_update', array( $this, 'log_profile_update' ), 10, 2 );
		add_action( 'save_post', array( $this, 'log_save_post' ), 10, 3 );
		add_action( 'updated_option', array( $this, 'log_updated_option' ), 10, 3 );
		add_action( 'user_register', array( $this, 'log_user_register' ), 10, 1 );
		add_action( 'delete_user', array( $this, 'log_delete_user' ), 10, 1 );
	}

	/**
	 * Create custom table for audit logs.
	 */
	public function create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pmc_audit_log';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				user varchar(255) DEFAULT '' NOT NULL,
				ip varchar(100) DEFAULT '' NOT NULL,
				action varchar(255) DEFAULT '' NOT NULL,
				details longtext NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	/**
	 * Log plugin activation.
	 *
	 * @param string $plugin       Path to the main plugin file from plugins directory.
	 * @param bool   $network_wide Whether to enable the plugin for all sites in the network.
	 */
	public function log_plugin_activation( $plugin, $network_wide = false ) {
		$this->log_event( 'activated_plugin', array( 'plugin' => $plugin, 'network_wide' => $network_wide ) );
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @param string $plugin       Path to the main plugin file from plugins directory.
	 * @param bool   $network_wide Whether to disable the plugin for all sites in the network.
	 */
	public function log_plugin_deactivation( $plugin, $network_wide = false ) {
		$this->log_event( 'deactivated_plugin', array( 'plugin' => $plugin, 'network_wide' => $network_wide ) );
	}

	/**
	 * Log user profile update.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Object containing user's data prior to update.
	 */
	public function log_profile_update( $user_id, $old_user_data = null ) {
		$this->log_event( 'profile_update', array( 'user_id' => $user_id ) );
	}

	/**
	 * Log post save/update.
	 */
	public function log_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$status = $update ? 'Updated' : 'Created';
		$this->log_event( 'save_post', "Post {$status}: {$post->post_title} (ID: {$post_id})" );
	}

	/**
	 * Log option update.
	 */
	public function log_updated_option( $option, $old_value, $value ) {
		if ( strpos( $option, '_transient' ) !== false || strpos( $option, 'cron' ) !== false ) {
			return;
		}
		$this->log_event( 'updated_option', "Option updated: {$option}" );
	}

	/**
	 * Log user registration.
	 */
	public function log_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		$username = $user ? $user->user_login : "ID {$user_id}";
		$this->log_event( 'user_register', "New user registered: {$username}" );
	}

	/**
	 * Log user deletion.
	 */
	public function log_delete_user( $id ) {
		$user = get_userdata( $id );
		$username = $user ? $user->user_login : "ID {$id}";
		$this->log_event( 'delete_user', "User deleted: {$username}" );
	}

	public function log_action( $action, $details ) {
		$this->log_event( $action, $details );
	}

	/**
	 * Log an event.
	 *
	 * @param string $action  Action name.
	 * @param mixed  $details Additional details or data.
	 */
	private function log_event( $action, $details ) {
		if ( is_array( $details ) ) {
			$details = wp_json_encode( $details );
		}

		$current_user = wp_get_current_user();
		$user_info    = ( $current_user && $current_user->exists() ) ? $current_user->user_login : 'System/Unknown';
		$time         = current_time( 'mysql' );
		$ip           = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		global $wpdb;
		$table_name = $wpdb->prefix . 'pmc_audit_log';
		$wpdb->insert(
			$table_name,
			array(
				'time'    => $time,
				'user'    => $user_info,
				'ip'      => $ip,
				'action'  => $action,
				'details' => $details,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}
}

new Pecodex_Audit();
