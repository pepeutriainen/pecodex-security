<?php
/**
 * Pecodex Lockdown Class
 */

class Pecodex_Lockdown {

    /**
     * Triggers a lockdown by writing a .maintenance file to take the site offline for 24 hours.
     */
    public static function trigger_lockdown() {
        if ( ! defined( 'ABSPATH' ) ) {
            return false;
        }

        $maintenance_file = ABSPATH . '.maintenance';
        $content = '<?php $upgrading = time() + 86400; ?>';

        return file_put_contents( $maintenance_file, $content ) !== false;
    }
}
