<?php

require_once 'duo_universal_settings.php';
require_once 'duo_universal_utilities.php';
require_once 'duo_universal_wordpress_helper.php';
require_once 'vendor/autoload.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;


// expire in 48hrs
const DUOUP_TRANSIENT_EXPIRATION = 48*60*60;

class DuoUniversalWordpressPlugin
{
    public function __construct(
        $duoup_utils,
        $duoup_client
    ) {
        $this->duoup_client = $duoup_client;
        $this->duoup_utils = $duoup_utils;
        $this->duoup_wordpress_helper = $duoup_utils->wordpress_helper;
    }
    // Sets a user's auth state
    // user: username of the user to update
    // status: whether or not an authentication is in progress or is completed ("in-progress" or "authenticated")
    function duoup_update_user_auth_status($duoup_user, $duoup_status, $duoup_redirect_url="", $duoup_oidc_state=null)
    {
        $this->duoup_wordpress_helper->set_transient("duo_auth_".$duoup_user."_status", $duoup_status, DUOUP_TRANSIENT_EXPIRATION);
        if($duoup_redirect_url) {
            $this->duoup_wordpress_helper->set_transient("duo_auth_".$duoup_user."_redirect_url", $duoup_redirect_url, DUOUP_TRANSIENT_EXPIRATION);
        }
        if($duoup_oidc_state) {
            // we need to track the state in two places so we can clean up later
            $this->duoup_wordpress_helper->set_transient("duo_auth_".$duoup_user."_oidc_state", $duoup_oidc_state, DUOUP_TRANSIENT_EXPIRATION);
            $this->duoup_wordpress_helper->set_transient("duo_auth_state_$duoup_oidc_state", $duoup_user, DUOUP_TRANSIENT_EXPIRATION);
        }
    }

    function duoup_debug_log($duoup_log_str)
    {
        return $this->duoup_utils->duo_debug_log($duoup_log_str);
    }

    function duoup_get_user_auth_status($duoup_user)
    {
        return $this->duoup_wordpress_helper->get_transient("duo_auth_".$duoup_user."_status");
    }

    function duoup_duo_verify_auth_status($duoup_user)
    {
        return ($this->duoup_get_user_auth_status($duoup_user) == "authenticated");
    }

    function duoup_get_username_from_oidc_state($duoup_oidc_state)
    {
        return $this->duoup_wordpress_helper->get_transient("duo_auth_state_$duoup_oidc_state");
    }

    function duoup_get_redirect_url($duoup_user)
    {
        return $this->duoup_wordpress_helper->get_transient("duo_auth_".$duoup_user."_redirect_url");
    }

    function duoup_clear_user_auth($duoup_user)
    {
        $duoup_username = $duoup_user->user_login;
        try {
            $duoup_oidc_state = $this->duoup_wordpress_helper->get_transient("duo_auth_".$duoup_username."_oidc_state");

            $this->duoup_wordpress_helper->delete_transient("duo_auth_".$duoup_username."_status");
            $this->duoup_wordpress_helper->delete_transient("duo_auth_".$duoup_username."_oidc_state");
            $this->duoup_wordpress_helper->delete_transient("duo_auth_state_$duoup_oidc_state");
            $this->duoup_wordpress_helper->delete_transient("duo_auth_".$duoup_username."_redirect_url");
        } catch (Exception $e) {
            // there's not much we can do but we shouldn't fail the logout because of this
            $this->duoup_debug_log($e->getMessage());
        };
    }

    function clear_current_user_auth()
    {
        $duoup_user = $this->duoup_wordpress_helper->wp_get_current_user();
        $this->duoup_clear_user_auth($duoup_user);
    }

    function duoup_get_page_url()
    {
        $duoup_protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $duoup_protocol.$_SERVER['HTTP_HOST'].$this->duoup_utils->duo_get_uri();
    }

    function duoup_exit()
    {
        exit();
    }

    function duoup_error_log($duoup_err_str, int $duoup_err_type=0, $duoup_err_destination=null, $duoup_err_headers=null)
    {
        error_log($duoup_err_str, $duoup_err_type, $duoup_err_destination, $duoup_err_headers);
    }

    function duoup_start_second_factor($duoup_user)
    {
        $this->duoup_client->healthCheck();

        $duoup_oidc_state = $this->duoup_client->generateState();
        $duoup_redirect_url = $this->duoup_get_page_url();
        $this->duoup_client->redirect_url = $duoup_redirect_url;
        $this->duoup_update_user_auth_status($duoup_user->user_login, "in-progress", $duoup_redirect_url, $duoup_oidc_state);

        $this->duoup_wordpress_helper->wp_logout();
        $duoup_prompt_uri = $this->duoup_client->createAuthUrl($duoup_user->user_login, $duoup_oidc_state);
        $this->duoup_wordpress_helper->wp_redirect($duoup_prompt_uri);
        $this->duoup_exit();
    }

    function duoup_authenticate_user($duoup_user="", $duoup_username="", $duoup_password="")
    {
        // play nicely with other plugins if they have higher priority than us
        if (is_a($duoup_user, 'WP_User')) {
            return $duoup_user;
        }

        if (!$this->duoup_utils->duo_auth_enabled()) {
                $this->duoup_debug_log('Duo not enabled, skipping 2FA.');
            return;
        }

        if (isset($_GET['duo_code'])) {
            //secondary auth
            if (isset($_GET["error"])) {
                $duoup_error_msg = $_GET["error"] . ":" . $_GET["error_description"];
                $duoup_error = $this->duoup_wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->duoup_wordpress_helper->translate("<strong>ERROR</strong>: $duoup_error_msg")
                );
                $this->duoup_debug_log($duoup_error_msg);
                return $duoup_error;
            }

            if (!isset($_GET["state"])) {
                $duoup_error_msg = "Missing state";
                $duoup_error = $this->duoup_wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->duoup_wordpress_helper->translate("<strong>ERROR</strong>: $duoup_error_msg")
                );
                $this->duoup_debug_log($duoup_error_msg);
                return $duoup_error;
            }
            $this->duoup_debug_log('Doing secondary auth');

            // Get authorization token to trade for 2FA
            $duoup_code = $_GET["duo_code"];

            // Get state to verify consistency and originality
            $duoup_state = $_GET["state"];

            // Retrieve the previously stored state and username from the session
            $duoup_associated_user = $this->duoup_get_username_from_oidc_state($duoup_state);

            if (empty($duoup_associated_user)) {
                $duoup_error_msg = "No saved state please login again";
                $duoup_error = $this->duoup_wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->duoup_wordpress_helper->translate("<strong>ERROR</strong>: $duoup_error_msg")
                );
                $this->duoup_debug_log($duoup_error_msg);
                return $duoup_error;
            }
            try {
                // Update redirect URL to be one associated with initial authentication
                $this->duoup_client->redirect_url = $this->duoup_get_redirect_url($duoup_associated_user);
                $duoup_decoded_token = $this->duoup_client->exchangeAuthorizationCodeFor2FAResult($duoup_code, $duoup_associated_user);
            } catch (Duo\DuoUniversal\DuoException $e) {
                $this->duoup_debug_log($e->getMessage());
                $duoup_error_msg = "Error decoding Duo result. Confirm device clock is correct.";
                $duoup_error = $this->duoup_wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->duoup_wordpress_helper->translate("<strong>ERROR</strong>: $duoup_error_msg")
                );
                $this->duoup_debug_log($duoup_error_msg);
                return $duoup_error;
            }
            $this->duoup_debug_log("Completed secondary auth for $duoup_associated_user");
            $this->duoup_update_user_auth_status($duoup_associated_user, "authenticated");
            $duoup_user = $this->duoup_wordpress_helper->WP_User(0, $duoup_associated_user);
            return $duoup_user;
        }

        if (strlen($duoup_username) > 0) {
            // primary auth
            // Don't use get_user_by(). It doesn't return a WP_User object if wordpress version < 3.3
            $duoup_user = $this->duoup_wordpress_helper->WP_User(0, $duoup_username);
            if (!$duoup_user) {
                $this->duoup_error_log("Failed to retrieve WP user $duoup_username");
                return;
            }
            if(!$this->duoup_utils->duo_role_require_mfa($duoup_user)) {
                $this->duoup_debug_log("Skipping 2FA for user: $duoup_username with roles: " . print_r($duoup_user->roles, true));
                $this->duoup_update_user_auth_status($duoup_user->user_login, "authenticated");
                return;
            }

            $this->duoup_debug_log("Doing primary authentication");
            $this->duoup_wordpress_helper->remove_action('authenticate', 'wp_authenticate_username_password', 20);
            $duoup_user = $this->duoup_wordpress_helper->wp_authenticate_username_password(null, $duoup_username, $duoup_password);
            if (!is_a($duoup_user, 'WP_User')) {
                // on error, return said error (and skip the remaining plugin chain)
                return $duoup_user;
            } else {
                $this->duoup_debug_log("Primary auth succeeded, starting second factor for $duoup_username");
                $this->duoup_update_user_auth_status($duoup_user->user_login, "in-progress");
                try {
                    $this->duoup_start_second_factor($duoup_user);
                } catch (Duo\DuoUniversal\DuoException $e) {
                    $this->duoup_debug_log($e->getMessage());
                    if ($this->duoup_utils->duo_get_option("duo_failmode") == "open") {
                        // If we're failing open, errors in 2FA still allow for success
                        $this->duoup_debug_log("Login 'Successful', but 2FA Not Performed. Confirm Duo client/secret/host values are correct");
                        $this->duoup_update_user_auth_status($duoup_user->user_login, "authenticated");
                        return $duoup_user;
                    } else {
                        $duoup_error_msg = "2FA Unavailable. Confirm Duo client/secret/host values are correct";
                        $duoup_error = $this->duoup_wordpress_helper->WP_Error(
                            'Duo authentication_failed',
                            $this->duoup_wordpress_helper->translate("<strong>Error</strong>: $duoup_error_msg")
                        );
                        $this->duoup_debug_log($duoup_error_msg);
                        $this->duoup_clear_user_auth($duoup_user);
                        return $duoup_error;
                    }
                }
            }
        }
        $this->duoup_debug_log('Starting primary authentication');
    }


    function duoup_duo_verify_auth()
    {
        /*
        Verify the user is authenticated with Duo. Start 2FA otherwise
        */
        if (! $this->duoup_utils->duo_auth_enabled()) {
            // XXX do we still need this skipping logic?
            if ($this->duoup_wordpress_helper->is_multisite()) {
                $duoup_site_info = $this->duoup_wordpress_helper->get_current_site();
                $this->duoup_debug_log("Duo not enabled on " . $duoup_site_info->site_name);
            }
            else {
                $this->duoup_debug_log('Duo not enabled, skip auth check.');
            }
            return;
        }

        if($this->duoup_wordpress_helper->is_user_logged_in()) {
            $duoup_user = $this->duoup_wordpress_helper->wp_get_current_user();
            $this->duoup_debug_log("Verifying auth state for user: $duoup_user->user_login");
            if ($this->duoup_utils->duo_role_require_mfa($duoup_user) && !$this->duoup_duo_verify_auth_status($duoup_user->user_login)) {
                $this->duoup_debug_log("User not authenticated with Duo. Starting second factor for: $duoup_user->user_login");
                $this->duoup_start_second_factor($duoup_user);
            }
            $this->duoup_debug_log("User $duoup_user->user_login allowed");
        }
    }
}
?>