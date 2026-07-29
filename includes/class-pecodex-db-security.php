<?php
/**
 * Database Security Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_DB_Security {

	/**
	 * Change database table prefix
	 *
	 * @param string $new_prefix The new table prefix.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function change_prefix( $new_prefix ) {
		global $wpdb;

		if ( empty( $new_prefix ) || preg_match( '|[^a-z0-9_]|i', $new_prefix ) ) {
			return new WP_Error( 'invalid_prefix', 'Invalid database prefix. Only letters, numbers, and underscores are allowed.' );
		}

		$old_prefix = $wpdb->prefix;

		if ( $new_prefix === $old_prefix ) {
			return new WP_Error( 'same_prefix', 'New prefix is the same as the old one.' );
		}

		// 1. Rename tables
		$tables = $wpdb->get_results( "SHOW TABLES LIKE '{$old_prefix}%'", ARRAY_N );
		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$table_name = $table[0];
				$new_table_name = substr_replace( $table_name, $new_prefix, 0, strlen( $old_prefix ) );
				$wpdb->query( "RENAME TABLE `{$table_name}` TO `{$new_table_name}`" );
			}
		}

		// Update $wpdb->prefix dynamically for the following queries
		$wpdb->set_prefix( $new_prefix );

		// 2. Update options table (e.g. wp_user_roles)
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->options}` SET `option_name` = REPLACE(`option_name`, %s, %s) WHERE `option_name` LIKE %s",
			$old_prefix,
			$new_prefix,
			$old_prefix . '%'
		) );

		// 3. Update usermeta table (e.g. wp_capabilities, wp_user_level)
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->usermeta}` SET `meta_key` = REPLACE(`meta_key`, %s, %s) WHERE `meta_key` LIKE %s",
			$old_prefix,
			$new_prefix,
			$old_prefix . '%'
		) );

		// 4. Update wp-config.php
		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( ! file_exists( $wp_config_path ) ) {
			if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
				$wp_config_path = dirname( ABSPATH ) . '/wp-config.php';
			}
		}

		if ( file_exists( $wp_config_path ) && is_writable( $wp_config_path ) ) {
			$config_content = file_get_contents( $wp_config_path );
			// Use regex to replace $table_prefix = 'wp_';
			$config_content = preg_replace(
				'/(?:\$table_prefix\s*=\s*)([\'"])(.*?)\1;/i',
				'$table_prefix = \'' . $new_prefix . '\';',
				$config_content
			);
			file_put_contents( $wp_config_path, $config_content );
		} else {
			return new WP_Error( 'config_not_writable', 'wp-config.php is not writable. Please update the $table_prefix manually.' );
		}

		return true;
	}

	/**
	 * Backup the database
	 *
	 * @return string|WP_Error Path to the backup file or WP_Error.
	 */
	public static function backup_database() {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/pmc-backups/';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Secure the backup directory
		if ( ! file_exists( $backup_dir . '.htaccess' ) ) {
			file_put_contents( $backup_dir . '.htaccess', "Deny from all\nOptions -Indexes\n" );
		}
		if ( ! file_exists( $backup_dir . 'index.php' ) ) {
			file_put_contents( $backup_dir . 'index.php', "<?php\n// Silence is golden\n" );
		}

		$hash     = substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
		$filename = 'backup-' . date( 'Y-m-d-H-i-s' ) . '-' . $hash . '.sql';
		$filepath = $backup_dir . $filename;

		// Try mysqldump
		$has_mysqldump = false;
		if ( function_exists( 'exec' ) ) {
			$output = array();
			$return_var = 0;
			@exec( 'mysqldump --version', $output, $return_var );
			if ( $return_var === 0 ) {
				$has_mysqldump = true;
			}
		}

		if ( $has_mysqldump ) {
			// Extract host and port if DB_HOST contains port
			$db_host = DB_HOST;
			$db_port = '';
			if ( strpos( $db_host, ':' ) !== false ) {
				list( $db_host, $db_port ) = explode( ':', $db_host );
			}

			$cmd = 'mysqldump -u ' . escapeshellarg( DB_USER );
			if ( defined( 'DB_PASSWORD' ) && DB_PASSWORD ) {
				$cmd .= ' -p' . escapeshellarg( DB_PASSWORD );
			}
			$cmd .= ' -h ' . escapeshellarg( $db_host );
			if ( $db_port ) {
				$cmd .= ' -P ' . escapeshellarg( $db_port );
			}
			$cmd .= ' ' . escapeshellarg( DB_NAME ) . ' > ' . escapeshellarg( $filepath );

			@exec( $cmd, $output, $return_var );

			if ( $return_var === 0 && file_exists( $filepath ) && filesize( $filepath ) > 0 ) {
				return $filepath;
			}
		}

		// Fallback to basic PHP SQL dump
		return self::php_sql_dump( $filepath );
	}

	/**
	 * Basic PHP SQL dump fallback
	 *
	 * @param string $filepath The path to save the SQL dump.
	 * @return string|WP_Error Path on success, WP_Error on failure.
	 */
	private static function php_sql_dump( $filepath ) {
		global $wpdb;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		if ( empty( $tables ) ) {
			return new WP_Error( 'no_tables', 'No tables found in database.' );
		}

		$fp = fopen( $filepath, 'w' );
		if ( ! $fp ) {
			return new WP_Error( 'write_error', 'Cannot write to backup file.' );
		}

		fwrite( $fp, "-- Database Backup\n" );
		fwrite( $fp, "-- Generated: " . date( 'Y-m-d H:i:s' ) . "\n\n" );

		foreach ( $tables as $table ) {
			$table_name = $table[0];
			
			// Create table statement
			$create_stmt = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N );
			if ( isset( $create_stmt[1] ) ) {
				fwrite( $fp, "DROP TABLE IF EXISTS `{$table_name}`;\n" );
				fwrite( $fp, $create_stmt[1] . ";\n\n" );
			}

			// Dump data
			$rows = $wpdb->get_results( "SELECT * FROM `{$table_name}`", ARRAY_N );
			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_values = array();
					foreach ( $row as $val ) {
						if ( is_null( $val ) ) {
							$row_values[] = 'NULL';
						} else {
							$row_values[] = "'" . esc_sql( $val ) . "'";
						}
					}
					$row_str = implode( ', ', $row_values );
					fwrite( $fp, "INSERT INTO `{$table_name}` VALUES ({$row_str});\n" );
				}
				fwrite( $fp, "\n" );
			}
		}

		fclose( $fp );
		return $filepath;
	}

	/**
	 * Check if table prefix is the default 'wp_'
	 *
	 * @return string|bool Warning message if prefix is wp_, false otherwise.
	 */
	public static function check_table_prefix() {
		global $wpdb;

		if ( $wpdb->prefix === 'wp_' ) {
			// Save it to an option as a warning
			update_option( 'pecodex_db_prefix_warning', true );
			return 'Warning: Database prefix is still set to the default "wp_". It is recommended to change it for better security.';
		}

		delete_option( 'pecodex_db_prefix_warning' );
		return false;
	}

	/**
	 * Backup the database schema
	 *
	 * @return string|WP_Error Path to the schema backup file or WP_Error.
	 */
	public static function backup_database_schema() {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/pmc-schema-backups/';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Secure the backup directory
		if ( ! file_exists( $backup_dir . '.htaccess' ) ) {
			file_put_contents( $backup_dir . '.htaccess', "Deny from all\nOptions -Indexes\n" );
		}
		if ( ! file_exists( $backup_dir . 'index.php' ) ) {
			file_put_contents( $backup_dir . 'index.php', "<?php\n// Silence is golden\n" );
		}

		$hash     = substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
		$filename = 'schema-backup-' . date( 'Y-m-d-H-i-s' ) . '-' . $hash . '.sql';
		$filepath = $backup_dir . $filename;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		if ( empty( $tables ) ) {
			return new WP_Error( 'no_tables', 'No tables found in database.' );
		}

		$fp = fopen( $filepath, 'w' );
		if ( ! $fp ) {
			return new WP_Error( 'write_error', 'Cannot write to schema backup file.' );
		}

		fwrite( $fp, "-- Database Schema Backup\n" );
		fwrite( $fp, "-- Generated: " . date( 'Y-m-d H:i:s' ) . "\n\n" );

		foreach ( $tables as $table ) {
			$table_name = $table[0];
			
			// Create table statement
			$create_stmt = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N );
			if ( isset( $create_stmt[1] ) ) {
				fwrite( $fp, "DROP TABLE IF EXISTS `{$table_name}`;\n" );
				fwrite( $fp, $create_stmt[1] . ";\n\n" );
			}
		}

		fclose( $fp );
		return $filepath;
	}
}
