<?php
defined('ABSPATH') or die('Direct Access Denied');

/*
Plugin Name: Duo Universal Two-Factor Authentication
Plugin URI: http://wordpress.org/extend/plugins/duo-wordpress/
Description: This plugin enables Duo two-factor authentication for WordPress logins.
Version: 2.5.7
Author: Duo Security
Author URI: http://www.duosecurity.com
License: GPL2
*/

/*
Copyright 2014 Duo Security <duo_web@duosecurity.com>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

    require_once('duo_settings.php');
    require_once('utilities.php');

    # We may or may not even need cookies
    $GLOBALS['DuoAuthCookieName'] = 'duo_wordpress_auth_cookie';
    $GLOBALS['DuoSecAuthCookieName'] = 'duo_secure_wordpress_auth_cookie';
    $GLOBALS['DuoDebug'] = false;

    // expire in 48hrs
    const DUO_TRANSIENT_EXPIRATION = 48*60*60;

    // Sets a user's auth state
    // user: username of the user to update
    // status: most recent phase of authentication that has been completed ("primary", "secondary" or "authenticated")
    function update_user_auth_status($user, $status, $oidc_state=NULL) {
        set_transient("duo_auth_".$user."_status", $status, DUO_TRANSIENT_EXPIRATION);
        set_transient("duo_auth_".$user."_oidc_state", $oidc_state, DUO_TRANSIENT_EXPIRATION);
    }

    function clear_current_user_auth($user) {
        $user = wp_get_current_user();
        // technically these could fail. What do?
        delete_transient("duo_auth_".$user->login_status."_status");
        delete_transient("duo_auth_".$user->login_status."_oidc_state");
    }

    function duo_start_second_factor($user, $redirect_to=NULL){
        // TODO not sure if we need this redirect
        if (!$redirect_to){
            // Some custom themes do not provide the redirect_to value
            // Admin page is a good default
            $redirect_to = isset( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : admin_url();
        }

        wp_logout();
        exit();
    }
    
    function duo_authenticate_user($user="", $username="", $password="") {
        // play nicely with other plugins if they have higher priority than us
        if (is_a($user, 'WP_User')) {
            return $user;
        }

        if (!duo_auth_enabled()){
            duo_debug_log('Duo not enabled, skipping 2FA.');
            return;
        }

        // if (primary authentication completed) {
        // TODO
        // }

        if (strlen($username) > 0) {
            // primary auth
            // Don't use get_user_by(). It doesn't return a WP_User object if wordpress version < 3.3
            $user = new WP_User(0, $username);
            if (!$user) {
                error_log("Failed to retrieve WP user $username");
                return;
            }
            if(!duo_role_require_mfa($user)){
                duo_debug_log("Skipping 2FA for user: $username with roles: " . print_r($user->roles, true));
                update_user_auth_status($user->user_login, "authenticated");
                return;
            }

            remove_action('authenticate', 'wp_authenticate_username_password', 20);
            $user = wp_authenticate_username_password(NULL, $username, $password);
            if (!is_a($user, 'WP_User')) {
                // on error, return said error (and skip the remaining plugin chain)
                return $user;
            } else {
                duo_debug_log("Primary auth succeeded, starting second factor for $username");
                update_user_auth_status($user->user_login, "primary");
                duo_start_second_factor($user);
            }
        }
        duo_debug_log('Starting primary authentication');
    }

    function duo_verify_auth(){
    /*
        Verify the user is authenticated with Duo. Start 2FA otherwise
    */
        if (! duo_auth_enabled()){
            // TODO do we still need this skipping logic?
            if (is_multisite()) {
                $site_info = get_current_site();
                duo_debug_log("Duo not enabled on " . $site_info->site_name . ', skip cookie check.');
            }
            else {
                duo_debug_log('Duo not enabled, skip auth check.');
            }
            return;
        }

        if(is_user_logged_in()){
            $user = wp_get_current_user();
            duo_debug_log("Verifying auth state for user: $user->user_login");
            if (duo_role_require_mfa($user) and !duo_verify_auth_status($user->user_login)){
                duo_debug_log("User not authenticated with Duo. Starting second factor for: $user->user_login");
                duo_start_second_factor($user, duo_get_uri());
            }
            duo_debug_log("User $user->user_login allowed");
        }
    }

    function duo_verify_auth_status($user) {
        $status = get_transient("duo_auth_".$user."_status");
        return ($status == "authenticated");
    }

    /*-------------XML-RPC Features-----------------*/
    
    if(duo_get_option('duo_xmlrpc', 'off') == 'off') {
        add_filter( 'xmlrpc_enabled', '__return_false' );
    }

    /*-------------Register WordPress Hooks-------------*/

    add_action('init', 'duo_verify_auth', 10);

    add_action('clear_auth_cookie', 'clear_current_user_auth', 10);

    add_filter('authenticate', 'duo_authenticate_user', 10, 3);
    
?>
