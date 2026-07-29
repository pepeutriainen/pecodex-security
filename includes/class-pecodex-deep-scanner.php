<?php
/**
 * Deep Scanner for Pecodex Media Control.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Deep_Scanner {

    /**
     * Quarantine a suspect file by moving it to a secure folder and appending .suspect extension.
     *
     * @param string $file_path Absolute path to the file.
     * @return bool True on success, false on failure.
     */
    public static function quarantine_file( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $quarantine_dir = $upload_dir['basedir'] . '/.quarantine';

        if ( ! file_exists( $quarantine_dir ) ) {
            wp_mkdir_p( $quarantine_dir );
        }

        // Add a .htaccess for extra security in the quarantine dir.
        if ( is_dir( $quarantine_dir ) && ! file_exists( $quarantine_dir . '/.htaccess' ) ) {
            @file_put_contents( $quarantine_dir . '/.htaccess', 'Deny from all' );
            @file_put_contents( $quarantine_dir . '/index.php', '<?php // Silence is golden' );
        }

        $file_name = wp_basename( $file_path );
        $new_file_path = trailingslashit( $quarantine_dir ) . $file_name . '.suspect';

        return rename( $file_path, $new_file_path );
    }

    /**
     * Initialize Deep Scanner
     */
    public static function init() {
        if ( ! get_option('pmc_deep_scanner_tables_created') ) {
            add_action( 'init', array( __CLASS__, 'create_tables' ) );
        }
        
        add_action( 'wp_ajax_pmc_deep_scan_start', array( __CLASS__, 'ajax_scan_start' ) );
        add_action( 'wp_ajax_pmc_deep_scan_step', array( __CLASS__, 'ajax_scan_step' ) );
        add_action( 'wp_ajax_pmc_deep_scan_action', array( __CLASS__, 'ajax_scan_action' ) );
    }

    /**
     * Create pmc_scan_item table
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        $charset_collate = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                file_path text NOT NULL,
                type varchar(50) NOT NULL,
                raw_data text,
                date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            dbDelta( $sql );
        }
        update_option('pmc_deep_scanner_tables_created', 1);
    }

    public static function ajax_scan_start() {
        check_ajax_referer('pmc_security_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

        global $wpdb, $wp_version;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        $wpdb->query("TRUNCATE TABLE $table_name"); // Clear previous results

        // 1. Fetch core checksums
        $locale = get_locale();
        $url = 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=' . $locale;
        $response = wp_remote_get( $url );
        
        $checksums = array();
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $data['checksums'] ) && is_array( $data['checksums'] ) ) {
                $checksums = $data['checksums'];
            }
        }
        update_option('pmc_core_checksums', $checksums);

        // 2. Build file list (core + wp-content)
        $files_to_scan = array();
        
        // Add core files
        foreach ($checksums as $file => $hash) {
            $files_to_scan[] = array('type' => 'core', 'path' => ABSPATH . $file, 'hash' => $hash);
        }

        // Add wp-content (plugins & themes) files
        $dirs_to_scan = array(WP_PLUGIN_DIR, get_theme_root());
        foreach ($dirs_to_scan as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files_to_scan[] = array('type' => 'content', 'path' => $file->getPathname());
                }
            }
        }

        update_option('pmc_scan_queue', $files_to_scan);
        update_option('pmc_scan_total', count($files_to_scan));
        
        wp_send_json_success(array('total' => count($files_to_scan)));
    }

    public static function ajax_scan_step() {
        check_ajax_referer('pmc_security_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

        $queue = get_option('pmc_scan_queue', array());
        if (empty($queue)) {
            wp_send_json_success(array('remaining' => 0, 'status' => 'complete'));
        }

        $batch_size = 50;
        $batch = array_splice($queue, 0, $batch_size);
        update_option('pmc_scan_queue', $queue);

        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        $malware_signatures = array(
            '/eval\s*\(\s*base64_decode\s*\(/i',
            '/gzinflate\s*\(\s*base64_decode\s*\(/i',
            '/preg_replace\s*\(\s*([\'"]).*?e.*?\1/i',
            '/\bstr_rot13\s*\(\s*base64_decode/i',
            '/\b(assert|system|passthru|exec|shell_exec)\s*\(\s*\$_/i',
            '/\b(file_put_contents|fopen)\s*\(\s*.*?\s*,\s*.*?base64_decode/i', // Tiedoston luonti encode-datasta
            '/strrev\s*\(\s*([\'"])edoced_46esab\1/i', // Obfuskointi: strrev('edoced_46esab')
            '/@include\s*\(\s*\\\\[a-z0-9_]+\s*\)/i', // Obfuskoitu include
            '/(\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\(\s*\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\))/i' // Kutsutaan funktiota suoraan parametrista, esim. $_GET['a']($_POST['b'])
        );

        foreach ($batch as $item) {
            if (!file_exists($item['path']) || !is_file($item['path']) || !is_readable($item['path'])) continue;

            if ($item['type'] === 'core') {
                $file_md5 = md5_file($item['path']);
                if ($file_md5 !== $item['hash']) {
                    $wpdb->insert($table_name, array(
                        'file_path' => $item['path'],
                        'type' => 'modified_core',
                        'raw_data' => json_encode(array('expected' => $item['hash'], 'actual' => $file_md5)),
                        'date' => current_time('mysql')
                    ));
                }
            } else if ($item['type'] === 'content') {
                $content = file_get_contents($item['path']);
                $found = false;
                foreach ($malware_signatures as $sig) {
                    if (preg_match($sig, $content)) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $wpdb->insert($table_name, array(
                        'file_path' => $item['path'],
                        'type' => 'malware',
                        'raw_data' => json_encode(array('reason' => 'Signature match')),
                        'date' => current_time('mysql')
                    ));
                }
            }
        }

        wp_send_json_success(array(
            'remaining' => count($queue),
            'total' => (int) get_option('pmc_scan_total', 0)
        ));
    }

    public static function ajax_scan_action() {
        check_ajax_referer('pmc_security_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

        $id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $action = isset($_POST['scan_action']) ? sanitize_text_field($_POST['scan_action']) : '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if (!$item) wp_send_json_error('Item not found');

        if ($action === 'ignore') {
            $wpdb->delete($table_name, array('id' => $id));
            wp_send_json_success('Ignored');
        } elseif ($action === 'quarantine') {
            self::quarantine_file($item->file_path);
            $wpdb->update($table_name, array('type' => 'quarantined'), array('id' => $id));
            wp_send_json_success('Quarantined');
        } elseif ($action === 'delete') {
            @unlink($item->file_path);
            $wpdb->delete($table_name, array('id' => $id));
            wp_send_json_success('Deleted');
        }

        wp_send_json_error('Invalid action');
    }

}

Pecodex_Deep_Scanner::init();
