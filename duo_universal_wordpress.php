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

    require_once('utilities.php');
    require_once('settings.php');

    # We may or may not even need cookies
    $GLOBALS['DuoAuthCookieName'] = 'duo_wordpress_auth_cookie';
    $GLOBALS['DuoSecAuthCookieName'] = 'duo_secure_wordpress_auth_cookie';
    $GLOBALS['DuoDebug'] = false;

    function duo_start_second_factor($user, $redirect_to=NULL){
        // TODO
    }
    
    function duo_authenticate_user($user="", $username="", $password="") {
        // play nicely with other plugins if they have higher priority than us
        if (is_a($user, 'WP_User')) {
            return $user;
        }

        if (! duo_auth_enabled()){
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
                return;
            }

            remove_action('authenticate', 'wp_authenticate_username_password', 20);
            $user = wp_authenticate_username_password(NULL, $username, $password);
            if (!is_a($user, 'WP_User')) {
                // on error, return said error (and skip the remaining plugin chain)
                return $user;
            } else {
                duo_debug_log("Primary auth succeeded, starting second factor for $username");
                duo_start_second_factor($user);
            }
        }
        duo_debug_log('Starting primary authentication');
    }


    // TODO we may or may not be using cookie verification in the final version of this
    // the body of this function might change considerably.
    function duo_verify_auth(){
    /*
        Verify the user is authenticated with Duo. Start 2FA otherwise
    */
        if (! duo_auth_enabled()){
            if (is_multisite()) {
                $site_info = get_current_site();
                duo_debug_log("Duo not enabled on " . $site_info->site_name . ', skip cookie check.');
            }
            else {
                duo_debug_log('Duo not enabled, skip cookie check.');
            }
            return;
        }

        if(is_user_logged_in()){
            $user = wp_get_current_user();
            duo_debug_log("Verifying second factor for user: $user->user_login URL: " .  duo_get_uri() . ' path: ' . COOKIEPATH . ' network path: ' . SITECOOKIEPATH . 'cookie domain: ' . COOKIE_DOMAIN . ' is SSL: ' . is_ssl());
            if (duo_role_require_mfa($user) and !duo_verify_cookie($user)){
                duo_debug_log("Duo cookie invalid for user: $user->user_login");
                duo_start_second_factor($user, duo_get_uri());
            }
            duo_debug_log("User $user->user_login allowed");
        }
    }

    function duo_unset_cookie(){
        global $DuoAuthCookieName;
        global $DuoSecAuthCookieName;
        setcookie($DuoAuthCookieName, '', strtotime('-1 day'), COOKIEPATH, COOKIE_DOMAIN);
        setcookie($DuoAuthCookieName, '', strtotime('-1 day'), SITECOOKIEPATH, COOKIE_DOMAIN);
        setcookie($DuoSecAuthCookieName, '', strtotime('-1 day'), COOKIEPATH, COOKIE_DOMAIN);
        setcookie($DuoSecAuthCookieName, '', strtotime('-1 day'), SITECOOKIEPATH, COOKIE_DOMAIN);
        duo_debug_log("Unset Duo cookie for path: " . COOKIEPATH . " network path: " . SITECOOKIEPATH . " on domain: " . COOKIE_DOMAIN);
    }

    if(!function_exists('hash_equals')) {
      function hash_equals($str1, $str2) {
        if(strlen($str1) != strlen($str2)) {
          return false;
        } else {
          $res = $str1 ^ $str2;
          $ret = 0;
          for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
          return !$ret;
        }
      }
    }

    /*-------------XML-RPC Features-----------------*/
    
    if(duo_get_option('duo_xmlrpc', 'off') == 'off') {
        add_filter( 'xmlrpc_enabled', '__return_false' );
    }

    /*-------------Register WordPress Hooks-------------*/

    add_action('init', 'duo_verify_auth', 10);

    add_action('clear_auth_cookie', 'duo_unset_cookie', 10);

    add_filter('authenticate', 'duo_authenticate_user', 10, 3);
    
?>
