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

        // Safety: Do not quarantine essential core/active theme root/framework files
        $norm = wp_normalize_path( $file_path );
        $theme_dir = wp_normalize_path( get_template_directory() );
        if ( strpos( $norm, $theme_dir . '/inc/' ) === 0 || strpos( $norm, $theme_dir . '/functions.php' ) === 0 ) {
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
        $new_file_path = trailingslashit( $quarantine_dir ) . $file_name . '_' . time() . '.suspect';

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
        add_action( 'wp_ajax_pmc_deep_scan_bulk_action', array( __CLASS__, 'ajax_scan_bulk_action' ) );
        add_action( 'wp_ajax_pmc_deep_scan_get_results', array( __CLASS__, 'ajax_get_results' ) );
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
        $wpdb->query("DELETE FROM $table_name WHERE type != 'quarantined' AND type != 'ignored'"); // Clear previous active results

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

        // 1b. Fetch Plugin Checksums
        $plugin_checksums = get_transient('pmc_plugin_checksums');
        if ( false === $plugin_checksums ) {
            $plugin_checksums = array();
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
            foreach ( $plugins as $plugin_file => $plugin_data ) {
                $slug = dirname($plugin_file);
                if (empty($slug) || $slug === '.') continue;
                $version = $plugin_data['Version'];
                $checksum_url = "https://downloads.wordpress.org/plugin-checksums/{$slug}/{$version}.json";
                $response = wp_remote_get($checksum_url, array('timeout' => 3));
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($data['files']) && is_array($data['files'])) {
                        foreach ($data['files'] as $file_path => $file_data) {
                            if (isset($file_data['md5'])) {
                                $plugin_checksums[wp_normalize_path(WP_PLUGIN_DIR . '/' . $slug . '/' . $file_path)] = $file_data['md5'];
                            }
                        }
                    }
                }
            }
            set_transient('pmc_plugin_checksums', $plugin_checksums, 12 * HOUR_IN_SECONDS);
        }

        // 2. Build file list (core + wp-content)
        $files_to_scan = array();
        
        // Add core files
        foreach ($checksums as $file => $hash) {
            $files_to_scan[] = array('type' => 'core', 'path' => ABSPATH . $file, 'hash' => $hash);
        }

        // Add wp-content (plugins & themes) files
        $dirs_to_scan = array(WP_PLUGIN_DIR, get_theme_root());
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['basedir'] ) ) {
            $dirs_to_scan[] = $upload_dir['basedir'];
        }

        foreach ($dirs_to_scan as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $path = wp_normalize_path($file->getPathname());
                    // Skip dependencies to reduce false positives and speed up scan
                    if (strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false) {
                        continue;
                    }
                    if (isset($plugin_checksums[$path])) {
                        $files_to_scan[] = array('type' => 'plugin', 'path' => $path, 'hash' => $plugin_checksums[$path]);
                    } else {
                        $files_to_scan[] = array('type' => 'content', 'path' => $path);
                    }
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
            '/\bstr_rot13\s*\(\s*base64_decode/i',
            '/\b(assert|system|passthru|exec|shell_exec)\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)/i',
            '/\b(file_put_contents|fopen)\s*\(\s*.*?\s*,\s*.*?base64_decode\s*\(\s*\$_(POST|GET|REQUEST)/i', // Tiedoston luonti encode-datasta suoraan
            '/strrev\s*\(\s*([\'"])edoced_46esab\1/i', // Obfuskointi: strrev('edoced_46esab')
            '/@include\s*\(\s*\\\\[a-z0-9_]+\s*\)/i', // Obfuskoitu include
            '/(\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\(\s*\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\))/i' // Kutsutaan funktiota suoraan parametrista, esim. $_GET['a']($_POST['b'])
        );

        $upload_dir = wp_upload_dir();
        $upload_basedir = wp_normalize_path($upload_dir['basedir']);

        $batch_paths = array_column($batch, 'path');
        if (!empty($batch_paths)) {
            $placeholders = implode(',', array_fill(0, count($batch_paths), '%s'));
            $existing_items = $wpdb->get_results($wpdb->prepare("SELECT file_path, type FROM $table_name WHERE file_path IN ($placeholders)", $batch_paths));
            $existing_states = array();
            foreach ($existing_items as $ei) {
                $existing_states[$ei->file_path] = $ei->type;
            }
        } else {
            $existing_states = array();
        }

        foreach ($batch as $item) {
            if (isset($existing_states[$item['path']]) && in_array($existing_states[$item['path']], array('quarantined', 'ignored'))) {
                continue;
            }
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
            } else if ($item['type'] === 'plugin') {
                $file_md5 = md5_file($item['path']);
                if ($file_md5 !== $item['hash']) {
                    $wpdb->insert($table_name, array(
                        'file_path' => $item['path'],
                        'type' => 'modified_plugin',
                        'raw_data' => json_encode(array('reason' => 'Tiedosto ei täsmää alkuperäisen lisäosan koodiin (Peukaloitu!)', 'expected' => $item['hash'], 'actual' => $file_md5)),
                        'date' => current_time('mysql')
                    ));
                }
            } else if ($item['type'] === 'content') {
                $path_normalized = wp_normalize_path($item['path']);
                
                // Extremely suspicious: PHP file in the uploads directory
                if ( strpos($path_normalized, $upload_basedir) === 0 ) {
                    // Ignore empty index.php files often used for directory protection
                    if ( filesize($item['path']) < 100 && basename($path_normalized) === 'index.php' ) {
                        // Safe
                    } else {
                        $wpdb->insert($table_name, array(
                            'file_path' => $item['path'],
                            'type' => 'malware',
                            'raw_data' => json_encode(array('reason' => 'PHP tiedosto uploads-kansiossa (Erittäin epäilyttävää)')),
                            'date' => current_time('mysql')
                        ));
                        continue;
                    }
                }

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
                        'raw_data' => json_encode(array('reason' => 'Allekirjoitus osuma')),
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
            $wpdb->update($table_name, array('type' => 'ignored'), array('id' => $id));
            wp_send_json_success('Ignored');
        } elseif ($action === 'restore') {
            $wpdb->update($table_name, array('type' => 'malware'), array('id' => $id));
            wp_send_json_success('Restored');
        } elseif ($action === 'quarantine') {
            self::quarantine_file($item->file_path);
            $wpdb->update($table_name, array('type' => 'quarantined'), array('id' => $id));
            wp_send_json_success('Quarantined');
        } elseif ($action === 'delete') {
            $norm = wp_normalize_path( $item->file_path );
            $theme_dir = wp_normalize_path( get_template_directory() );
            
            if ( file_exists($item->file_path) ) {
                if ( strpos( $norm, $theme_dir . '/inc/' ) === 0 || strpos( $norm, $theme_dir . '/functions.php' ) === 0 ) {
                    wp_send_json_error('Suojattu teematiedosto, poisto estetty. Käytä "Ohita"-painiketta jos haluat vain piilottaa ilmoituksen.');
                }
                @unlink($item->file_path);
            }
            
            $wpdb->delete($table_name, array('id' => $id));
            wp_send_json_success('Deleted');
        }

        wp_send_json_error('Invalid action');
    }

    public static function ajax_scan_bulk_action() {
        check_ajax_referer('pmc_security_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

        $raw_ids = isset($_POST['ids']) ? $_POST['ids'] : array();
        if (is_array($raw_ids)) {
            $ids = array_map('intval', $raw_ids);
        } else {
            $ids = array_map('intval', explode(',', sanitize_text_field($raw_ids)));
        }
        $ids = array_values(array_filter($ids));
        $action = isset($_POST['scan_action']) ? sanitize_text_field($_POST['scan_action']) : '';

        if (empty($ids) || empty($action)) {
            wp_send_json_error('Invalid data');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $items = $wpdb->get_results($wpdb->prepare("SELECT id, file_path FROM $table_name WHERE id IN ($placeholders)", $ids));

        $processed = 0;
        foreach ($items as $item) {
            if ($action === 'ignore') {
                $wpdb->update($table_name, array('type' => 'ignored'), array('id' => $item->id));
                $processed++;
            } elseif ($action === 'restore') {
                $wpdb->update($table_name, array('type' => 'malware'), array('id' => $item->id));
                $processed++;
            } elseif ($action === 'quarantine') {
                if (self::quarantine_file($item->file_path)) {
                    $wpdb->update($table_name, array('type' => 'quarantined'), array('id' => $item->id));
                    $processed++;
                } else {
                    // Even if file cannot be moved physically, mark quarantine state in DB
                    $wpdb->update($table_name, array('type' => 'quarantined'), array('id' => $item->id));
                    $processed++;
                }
            } elseif ($action === 'delete') {
                $norm = wp_normalize_path( $item->file_path );
                $theme_dir = wp_normalize_path( get_template_directory() );
                
                $can_delete = true;
                if ( file_exists($item->file_path) ) {
                    if ( strpos( $norm, $theme_dir . '/inc/' ) === 0 || strpos( $norm, $theme_dir . '/functions.php' ) === 0 ) {
                        $can_delete = false; // Protected theme file
                    } else {
                        @unlink($item->file_path);
                    }
                }
                
                if ($can_delete) {
                    $wpdb->delete($table_name, array('id' => $item->id));
                    $processed++;
                }
            }
        }

        wp_send_json_success(array('processed' => $processed, 'total' => count($ids)));
    }

    public static function ajax_get_results() {
        check_ajax_referer('pmc_security_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        
        $scan_tab = isset($_POST['scan_tab']) ? sanitize_text_field($_POST['scan_tab']) : 'active';
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $per_page = 30;
        $offset = ($paged - 1) * $per_page;

        if ($scan_tab === 'quarantined') {
            $where = "type = 'quarantined'";
        } elseif ($scan_tab === 'ignored') {
            $where = "type = 'ignored'";
        } else {
            $where = "type != 'quarantined' AND type != 'ignored'";
        }

        $total_items = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE $where");
        $scan_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
        $total_pages = ceil($total_items / $per_page);

        $count_active = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type != 'quarantined' AND type != 'ignored'");
        $count_quarantined = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type = 'quarantined'");
        $count_ignored = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE type = 'ignored'");

        ob_start();
        if (empty($scan_items)) {
            echo '<tr><td colspan="4">Epäilyttäviä tiedostoja ei löytynyt. Järjestelmä on puhdas!</td></tr>';
        } else {
            foreach ($scan_items as $item) {
                ?>
                <tr id="scan-item-<?php echo esc_attr($item->id); ?>">
                    <td style="width: 32px; padding: 12px 16px; text-align: center;">
                        <input type="checkbox" class="ps-scan-cb" value="<?php echo esc_attr($item->id); ?>" style="border-radius: 4px; cursor: pointer;">
                    </td>
                    <td style="max-width: 0; padding-right: 16px;">
                        <div style="display: flex; align-items: center; gap: 4px; background: #f8fafc; padding: 2px 4px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <button type="button" title="Vieritä vasemmalle" onclick="this.nextElementSibling.scrollBy({left: -150, behavior: 'smooth'})" style="background: transparent; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <div class="ps-path-scroll" style="flex: 1; font-family: monospace; font-size: 14px; line-height: 1.5; white-space: nowrap; overflow-x: auto; color: #334155; padding: 6px 4px;">
                                <?php echo esc_html($item->file_path); ?>
                            </div>
                            <button type="button" title="Vieritä oikealle" onclick="this.previousElementSibling.scrollBy({left: 150, behavior: 'smooth'})" style="background: transparent; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <?php if ($item->type === 'modified_core'): ?>
                            <span style="background: #fef08a; color: #854d0e; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Muokattu ydin</span>
                        <?php elseif ($item->type === 'malware'): ?>
                            <span style="background: #fecaca; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;">Haittaohj. allekirj.</span>
                        <?php else: ?>
                            <span style="background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: 700;"><?php echo esc_html($item->type); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="display: flex; gap: 6px;">
                        <?php if ($scan_tab === 'active'): ?>
                            <button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #d97706; border: 1px solid #fcd34d; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'quarantine')" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fff'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                Karanteeniin
                            </button>
                            <button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #dc2626; border: 1px solid #fca5a5; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'delete')" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                Poista
                            </button>
                            <button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #475569; border: 1px solid #cbd5e1; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'ignore')" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                                Ohita
                            </button>
                        <?php else: ?>
                            <button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #10b981; border: 1px solid #6ee7b7; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'restore')" onmouseover="this.style.background='#d1fae5'" onmouseout="this.style.background='#fff'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                Palauta
                            </button>
                            <button class="ps-btn ps-action-btn" style="padding: 6px 10px; font-size: 13px; font-weight: 500; background: #fff; color: #dc2626; border: 1px solid #fca5a5; cursor: pointer; border-radius: 6px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="window.pmcScanAction(<?php echo $item->id; ?>, 'delete')" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                Poista pysyvästi
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'paged' => $paged,
            'scan_tab' => $scan_tab,
            'count_active' => $count_active,
            'count_quarantined' => $count_quarantined,
            'count_ignored' => $count_ignored
        ));
    }

}

Pecodex_Deep_Scanner::init();
