<?php
/**
 * Pecodex Security WAF Bootstrap
 *
 * Tämän tiedoston on tarkoitus latautua auto_prepend_file -asetuksella ennen WordPressiä.
 */

if ( ! defined( 'PECODEX_WAF_LOADED' ) ) {
    define( 'PECODEX_WAF_LOADED', true );

    $pecodex_waf_path = dirname( __FILE__ ) . '/includes/class-pecodex-waf-core.php';

    if ( file_exists( $pecodex_waf_path ) ) {
        require_once $pecodex_waf_path;
        if ( class_exists( 'Pecodex_WAF_Core' ) ) {
            Pecodex_WAF_Core::run();
        }
    }
}
