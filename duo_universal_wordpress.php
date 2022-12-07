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
    require_once('vendor/autoload.php');

    use Duo\DuoUniversal\Client;

    # We may or may not even need cookies
    $GLOBALS['DuoAuthCookieName'] = 'duo_wordpress_auth_cookie';
    $GLOBALS['DuoSecAuthCookieName'] = 'duo_secure_wordpress_auth_cookie';
    $GLOBALS['DuoDebug'] = false;
    $DuoClient = NULL;

    // expire in 48hrs
    const DUO_TRANSIENT_EXPIRATION = 48*60*60;

    // Sets a user's auth state
    // user: username of the user to update
    // status: whether or not an authentication is in progress or is completed ("in-progress" or "authenticated")
    function update_user_auth_status($user, $status, $redirect_url="", $oidc_state=NULL) {
        set_transient("duo_auth_".$user."_status", $status, DUO_TRANSIENT_EXPIRATION);
        if($redirect_url) {
            set_transient("duo_auth_".$user."_redirect_url", $redirect_url, DUO_TRANSIENT_EXPIRATION);
        }
        if($oidc_state) {
            // we need to track the state in two places so we can clean up later
            set_transient("duo_auth_".$user."_oidc_state", $oidc_state, DUO_TRANSIENT_EXPIRATION);
            set_transient("duo_auth_state_$oidc_state", $user, DUO_TRANSIENT_EXPIRATION);
        }
    }

    function get_user_auth_status($user) {
        return get_transient("duo_auth_".$user."_status");
    }

    function get_username_from_oidc_state($oidc_state) {
        return get_transient("duo_auth_state_$oidc_state");
    }

    function get_redirect_url($user) {
        return get_transient("duo_auth_".$user."_redirect_url");
    }

    function clear_current_user_auth() {
        $user = wp_get_current_user();
        $username = $user->user_login;
        $oidc_state = get_transient("duo_auth_".$username."_oidc_state");
        // technically these could fail. What do?
        delete_transient("duo_auth_".$username."_status");
        delete_transient("duo_auth_".$username."_oidc_state");
        delete_transient("duo_auth_state_$oidc_state");
        delete_transient("duo_auth_".$username."_redirect_url");
    }

    function get_page_url() {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING'];
    }

    function duo_start_second_factor($user, $redirect_to=NULL){
        global $DuoClient;

        $DuoClient->healthCheck();

        $oidc_state = $DuoClient->generateState();
        $redirect_url = get_page_url();
        $DuoClient->redirect_url = $redirect_url;
        update_user_auth_status($user->user_login, "in-progress", $redirect_url, $oidc_state);

        wp_logout();
        $prompt_uri = $DuoClient->createAuthUrl($user->user_login, $oidc_state);
        wp_redirect($prompt_uri);
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

        if (isset($_GET['duo_code'])) {
            //secondary auth
            duo_debug_log('Doing secondary auth');
            if (isset($_GET["error"])) {
                $error_msg = $_GET["error"] . ":" . $_GET["error_description"];
                $error = new WP_Error('Duo authentication_failed',
                                     __("<strong>ERROR</strong>: $error_msg"));
                duo_debug_log('Error in URL');
                return $error;
            }

            if (!isset($_GET["duo_code"]) || !isset($_GET["state"])) {
                $error_msg = "Missing state or code";
                $error = new WP_Error('Duo authentication_failed',
                                     __("<strong>ERROR</strong>: $error_msg"));
                duo_debug_log('Error in params');
                return $error;
            }

            # Get authorization token to trade for 2FA
            $code = $_GET["duo_code"];

            # Get state to verify consistency and originality
            $state = $_GET["state"];

            # Retrieve the previously stored state and username from the session
            $associated_user = get_username_from_oidc_state($state);

            if (empty($associated_user)) {
                $error_msg = "No saved state please login again";
                $error = new WP_Error('Duo authentication_failed',
                                     __("<strong>ERROR</strong>: $error_msg"));
                duo_debug_log('Missing saved state');
                return $error;
            }
            try {
                global $DuoClient;
                // Update redirect URL to be one associated with initial authentication
                $DuoClient->redirect_url = get_redirect_url($associated_user);
                $decoded_token = $DuoClient->exchangeAuthorizationCodeFor2FAResult($code, $associated_user);
            } catch (Duo\DuoUniversal\DuoException $e) {
                duo_debug_log($e->getMessage());
                $error_msg = "Error decoding Duo result. Confirm device clock is correct.";
                $error = new WP_Error('Duo authentication_failed',
                                     __("<strong>ERROR</strong>: $error_msg"));
                duo_debug_log('Error in decoding');
                return $error;
            }
            duo_debug_log("Completed secondary auth for $associated_user");
            update_user_auth_status($associated_user, "authenticated");
            $user = new WP_User(0, $associated_user);
            return $user;
        }

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

            duo_debug_log("Doing primary authentication");
            remove_action('authenticate', 'wp_authenticate_username_password', 20);
            $user = wp_authenticate_username_password(NULL, $username, $password);
            if (!is_a($user, 'WP_User')) {
                // on error, return said error (and skip the remaining plugin chain)
                return $user;
            } else {
                duo_debug_log("Primary auth succeeded, starting second factor for $username");
                update_user_auth_status($user->user_login, "in-progress");
                try {
                    duo_start_second_factor($user);
                } catch (Duo\DuoUniversal\DuoException $e) {
                    duo_debug_log($e->getMessage());
                    if (duo_get_option("duo_failmode") == "open") {
                        # If we're failing open, errors in 2FA still allow for success
                        duo_debug_log("Login 'Successful', but 2FA Not Performed. Confirm Duo client/secret/host values are correct");
                        update_user_auth_status($user->user_login, "authenticated");
                        return $user;
                    } else {
                        # Otherwise the login fails and redirect user to the login page
                        # XXX should this be Lee facing?
                        duo_debug_log("2FA Unavailable. Confirm Duo client/secret/host values are correct");
                    }
                }
            }
        }
        duo_debug_log('Starting primary authentication');
    }

    function duo_verify_auth(){
    /*
        Verify the user is authenticated with Duo. Start 2FA otherwise
    */
        if (! duo_auth_enabled()){
            // XXX do we still need this skipping logic?
            if (is_multisite()) {
                $site_info = get_current_site();
                duo_debug_log("Duo not enabled on " . $site_info->site_name . ', skip cookie check.');
            }
            else {
                duo_debug_log('Duo not enabled, skip auth check.');
            }
            return;
        } else {
            global $DuoClient;
            $DuoClient = new Client(
                duo_get_option('duo_ikey'),
                duo_get_option('duo_skey'),
                duo_get_option('duo_host'),
                "",
            );
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
