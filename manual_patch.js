const fs = require('fs');
const file = 'c:/Users/pepeu/Local Sites/ht-toimistokalusteet2/app/public/wp-content/plugins/pecodex-security/includes/class-pecodex-security-api.php';
let content = fs.readFileSync(file, 'utf8');

// Replace 1
content = content.replace(
`					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE date > %s ORDER BY date ASC LIMIT %d",
						$since,
						$limit
					),
					ARRAY_A
				);`,
`					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE date > %s ORDER BY date DESC LIMIT %d",
						$since,
						$limit
					),
					ARRAY_A
				);
                // Käännetään takaisin kronologiseen järjestykseen, koska haemme uusimmat
                if ($logs) {
                    $logs = array_reverse($logs);
                }`
);

// Replace 2
content = content.replace(
`				$event['city']    = ! empty( $geo['city'] ) ? $geo['city'] : 'Unknown';
				$event['country'] = ! empty( $geo['country_code'] ) ? $geo['country_code'] : $event['country'];
			} else {
                mt_srand( crc32( $ip ) );
                $base_lat = (float) ( mt_rand( -5000, 6000 ) / 100.0 );
                $base_lng = (float) ( mt_rand( -12000, 12000 ) / 100.0 );
                
                mt_srand( crc32( $ip . $event_id ) );`,
`				$event['city']    = ! empty( $geo['city'] ) ? $geo['city'] : 'Unknown';
				$event['country'] = ! empty( $geo['country_code'] ) ? $geo['country_code'] : $event['country'];
			} else {
                // Jos IP on lokaali/yksityinen (esim. localhost testaus), levitetään ympäri maailmaa demotarkoituksessa
                $is_local = ( $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.') === 0 );
                
                if ($is_local) {
                    mt_srand( crc32( $event_id ) );
                    $base_lat = (float) ( mt_rand( -5000, 6000 ) / 100.0 );
                    $base_lng = (float) ( mt_rand( -12000, 12000 ) / 100.0 );
                } else {
                    mt_srand( crc32( $ip ) );
                    $base_lat = (float) ( mt_rand( -5000, 6000 ) / 100.0 );
                    $base_lng = (float) ( mt_rand( -12000, 12000 ) / 100.0 );
                }
                
                mt_srand( crc32( $ip . $event_id ) );`
);

// Replace 3
content = content.replace(
`			Pecodex_Traffic_Logger::process_logs( false );
		}
		$offset_hours = isset( $_POST['offset_hours'] ) ? (int) $_POST['offset_hours'] : 0;
		$target_date = $offset_hours > 0 ? gmdate('Y-m-d', time() - ($offset_hours * 3600)) : gmdate('Y-m-d');
		
		$events = array();`,
`			Pecodex_Traffic_Logger::process_logs( false );
		}
		$offset_hours = isset( $_POST['offset_hours'] ) ? (int) $_POST['offset_hours'] : 0;
        $show_all_day = !empty( $_POST['show_all_day'] );
		$target_date = $offset_hours > 0 ? gmdate('Y-m-d', time() - ($offset_hours * 3600)) : gmdate('Y-m-d');
        
        $since = null;
        $until = null;
        
        if ($offset_hours > 0 && !$show_all_day) {
            $target_time = time() - ( $offset_hours * HOUR_IN_SECONDS );
            $since = gmdate( 'Y-m-d H:i:s', $target_time - ( 2 * HOUR_IN_SECONDS ) );
            $until = gmdate( 'Y-m-d H:i:s', $target_time + ( 2 * HOUR_IN_SECONDS ) );
        }
		
		$events = array();`
);

// Replace 4
content = content.replace(
`		if ( $this->pmc_has_lockout_tables() ) {
			$table = $wpdb->prefix . 'pmc_lockout_log';
			$db_logs = $wpdb->get_results( $wpdb->prepare("SELECT *, 'lockout' AS source FROM {$table} WHERE DATE(date) = %s ORDER BY id DESC LIMIT 100", $target_date), ARRAY_A );
			if ( $db_logs ) {`,
`		if ( $this->pmc_has_lockout_tables() ) {
			$table = $wpdb->prefix . 'pmc_lockout_log';
			if ($since && $until) {
                $db_logs = $wpdb->get_results( $wpdb->prepare("SELECT *, 'lockout' AS source FROM {$table} WHERE date BETWEEN %s AND %s ORDER BY id DESC LIMIT 100", $since, $until), ARRAY_A );
            } else {
                $db_logs = $wpdb->get_results( $wpdb->prepare("SELECT *, 'lockout' AS source FROM {$table} WHERE DATE(date) = %s ORDER BY id DESC LIMIT 100", $target_date), ARRAY_A );
            }
			if ( $db_logs ) {`
);

// Replace 5
content = content.replace(
`        $traffic_table = $wpdb->prefix . 'pmc_traffic_log';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$traffic_table'" ) === $traffic_table ) {
            $traffic_logs = $wpdb->get_results( $wpdb->prepare("SELECT id, ip, time AS date, url, method, status, is_bad, country_iso_code, 'traffic' AS source FROM {$traffic_table} WHERE DATE(time) = %s ORDER BY id DESC LIMIT 500", $target_date), ARRAY_A );
            if ($traffic_logs) {`,
`        $traffic_table = $wpdb->prefix . 'pmc_traffic_log';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$traffic_table'" ) === $traffic_table ) {
            if ($since && $until) {
                $traffic_logs = $wpdb->get_results( $wpdb->prepare("SELECT id, ip, time AS date, url, method, status, is_bad, country_iso_code, 'traffic' AS source FROM {$traffic_table} WHERE time BETWEEN %s AND %s ORDER BY id DESC LIMIT 500", $since, $until), ARRAY_A );
            } else {
                $traffic_logs = $wpdb->get_results( $wpdb->prepare("SELECT id, ip, time AS date, url, method, status, is_bad, country_iso_code, 'traffic' AS source FROM {$traffic_table} WHERE DATE(time) = %s ORDER BY id DESC LIMIT 500", $target_date), ARRAY_A );
            }
            if ($traffic_logs) {`
);

fs.writeFileSync(file, content, 'utf8');
console.log('Applied missing API updates.');
