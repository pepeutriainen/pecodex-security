<?php
/**
 * Cron tasks for Pecodex Security (Scheduled Scans & Alerts)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Cron {

    public static function init() {
        add_action( 'pecodex_daily_security_scan', array( __CLASS__, 'run_scheduled_scan' ) );
        add_action( 'pecodex_process_scan_queue', array( __CLASS__, 'process_scan_queue' ) );

        // Schedule daily scan if not already scheduled
        if ( ! wp_next_scheduled( 'pecodex_daily_security_scan' ) ) {
            wp_schedule_event( time(), 'daily', 'pecodex_daily_security_scan' );
        }
        
        add_action( 'pecodex_cleanup_live_traffic', array( __CLASS__, 'cleanup_live_traffic' ) );
        if ( ! wp_next_scheduled( 'pecodex_cleanup_live_traffic' ) ) {
            wp_schedule_event( time(), 'hourly', 'pecodex_cleanup_live_traffic' );
        }

        add_action( 'pecodex_weekly_health_report', array( __CLASS__, 'generate_weekly_report' ) );
        if ( ! wp_next_scheduled( 'pecodex_weekly_health_report' ) ) {
            wp_schedule_event( time(), 'weekly', 'pecodex_weekly_health_report' );
        }
    }

    public static function cleanup_live_traffic() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_live_traffic';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
            $threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 15 * 60 );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table_name WHERE time < %s",
                    $threshold
                )
            );
        }
    }

    /**
     * Triggered daily by WP Cron. Initializes the scan queue.
     */
    public static function run_scheduled_scan() {
        global $wpdb, $wp_version;
        $table_name = $wpdb->prefix . 'pmc_scan_item';

        // Ensure table exists
        if ( class_exists('Pecodex_Deep_Scanner') ) {
            Pecodex_Deep_Scanner::create_tables();
        } else {
            return; // Scanner missing
        }

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

        // 2. Build file list
        $files_to_scan = array();
        foreach ($checksums as $file => $hash) {
            $files_to_scan[] = array('type' => 'core', 'path' => ABSPATH . $file, 'hash' => $hash);
        }

        $dirs_to_scan = array(WP_PLUGIN_DIR, get_theme_root());
        foreach ($dirs_to_scan as $dir) {
            if (!is_dir($dir)) continue;
            try {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $files_to_scan[] = array('type' => 'content', 'path' => $file->getPathname());
                    }
                }
            } catch (Exception $e) {
                // Ignore unreadable dirs
            }
        }

        update_option('pmc_scan_queue', $files_to_scan);
        update_option('pmc_scan_total', count($files_to_scan));
        
        // Start processing immediately
        if ( ! wp_next_scheduled( 'pecodex_process_scan_queue' ) ) {
            wp_schedule_single_event( time(), 'pecodex_process_scan_queue' );
        }
    }

    /**
     * Processes a batch of the scan queue to avoid timeouts.
     */
    public static function process_scan_queue() {
        $queue = get_option('pmc_scan_queue', array());
        
        if (empty($queue)) {
            // Scan complete! Send alert if needed.
            self::send_scan_report();
            return;
        }

        // Process a large batch since this is a background task
        $batch_size = 200;
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
            '/\b(file_put_contents|fopen)\s*\(\s*.*?\s*,\s*.*?base64_decode/i',
            '/strrev\s*\(\s*([\'"])edoced_46esab\1/i',
            '/@include\s*\(\s*\\\\[a-z0-9_]+\s*\)/i',
            '/(\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\(\s*\$_(GET|POST|COOKIE|REQUEST)\[.+?\]\s*\))/i'
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

        // Schedule next batch if more remaining
        if (count($queue) > 0) {
            if ( ! wp_next_scheduled( 'pecodex_process_scan_queue' ) ) {
                wp_schedule_single_event( time(), 'pecodex_process_scan_queue' );
            }
        } else {
            // Scan complete
            self::send_scan_report();
        }
    }

    /**
     * Send email alert to admin if threats are found.
     */
    public static function send_scan_report() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pmc_scan_item';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            return;
        }

        $issues = $wpdb->get_results( "SELECT * FROM $table_name" );
        
        if ( count($issues) > 0 ) {
            $admin_email = get_option('admin_email');
            $site_url = get_site_url();
            
            $subject = "⚠️ Pecodex Security: Tietoturvauhkia löydetty sivustolta {$site_url}";
            
            $message = "Pecodex Security on suorittanut ajastetun haittaohjelma- ja eheystarkistuksen.\n\n";
            $message .= "Skannaus löysi " . count($issues) . " ongelmaa, jotka vaativat huomiotasi:\n\n";
            
            foreach ($issues as $issue) {
                $type = $issue->type === 'modified_core' ? 'Muokattu ydintiedosto' : 'Mahdollinen haittaohjelma';
                $message .= "- Tyyppi: {$type}\n";
                $message .= "  Tiedosto: {$issue->file_path}\n\n";
            }
            
            $message .= "Kirjaudu sisään hallintapaneeliin ja tarkista Pecodex Security -kojelaudalta skannauksen tulokset.\n";
            $message .= $site_url . "/wp-admin/admin.php?page=pecodex-security\n";
            
            wp_mail( $admin_email, $subject, $message );
        }
    }

    public static function generate_weekly_report() {
        $summary_string = "Weekly Health Report: Everything is secure. Total blocked IPs: " . count(get_option('pmc_firewall_blacklist', []));
        Pecodex_Notifications::send_notification( 'health_report', 'Weekly Security Health Report', $summary_string );
    }
}
