<?php

class Pecodex_App_Sec {
    public function __construct() {
        // Block all XML-RPC completely
        add_filter('xmlrpc_enabled', '__return_false');
        add_action('init', array($this, 'block_xmlrpc'), 1);
        
        // Lockdown REST API for unauthenticated users
        add_filter('rest_authentication_errors', array($this, 'restrict_rest_api'));
    }

    public function block_xmlrpc() {
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            wp_die('XML-RPC Lockdown', 'Access Denied', array('response' => 403));
        }
    }

    public function restrict_rest_api($result) {
        // If a previous authentication check has already returned an error, return it.
        if (!empty($result)) {
            return $result;
        }

        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', 'REST API Lockdown', array('status' => 401));
        }

        return $result;
    }
}
