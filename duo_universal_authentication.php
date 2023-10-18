<?php

require_once 'duo_universal_settings.php';
require_once 'duo_universal_utilities.php';
require_once 'duo_universal_wordpress_helper.php';
require_once 'vendor/autoload.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversalWordpress;


// expire in 48hrs
const DUO_TRANSIENT_EXPIRATION = 48*60*60;

class DuoUniversalWordpressPlugin
{
    public function __construct(
        $duo_utils,
        $duo_client
    ) {
        $this->duo_client = $duo_client;
        $this->duo_utils = $duo_utils;
        $this->wordpress_helper = $duo_utils->wordpress_helper;
    }
    // Sets a user's auth state
    // user: username of the user to update
    // status: whether or not an authentication is in progress or is completed ("in-progress" or "authenticated")
    function update_user_auth_status($user, $status, $redirect_url="", $oidc_state=null)
    {
        $this->wordpress_helper->set_transient("duo_auth_".$user."_status", $status, DUO_TRANSIENT_EXPIRATION);
        if($redirect_url) {
            $this->wordpress_helper->set_transient("duo_auth_".$user."_redirect_url", $redirect_url, DUO_TRANSIENT_EXPIRATION);
        }
        if($oidc_state) {
            // we need to track the state in two places so we can clean up later
            $this->wordpress_helper->set_transient("duo_auth_".$user."_oidc_state", $oidc_state, DUO_TRANSIENT_EXPIRATION);
            $this->wordpress_helper->set_transient("duo_auth_state_$oidc_state", $user, DUO_TRANSIENT_EXPIRATION);
        }
    }

    function duo_debug_log($str)
    {
        return $this->duo_utils->duo_debug_log($str);
    }

    function get_user_auth_status($user)
    {
        return $this->wordpress_helper->get_transient("duo_auth_".$user."_status");
    }

    function duo_verify_auth_status($user)
    {
        return ($this->get_user_auth_status($user) == "authenticated");
    }

    function get_username_from_oidc_state($oidc_state)
    {
        return $this->wordpress_helper->get_transient("duo_auth_state_$oidc_state");
    }

    function get_redirect_url($user)
    {
        return $this->wordpress_helper->get_transient("duo_auth_".$user."_redirect_url");
    }

    function clear_user_auth($user)
    {
        $username = $user->user_login;
        try {
            $oidc_state = $this->wordpress_helper->get_transient("duo_auth_".$username."_oidc_state");

            $this->wordpress_helper->delete_transient("duo_auth_".$username."_status");
            $this->wordpress_helper->delete_transient("duo_auth_".$username."_oidc_state");
            $this->wordpress_helper->delete_transient("duo_auth_state_$oidc_state");
            $this->wordpress_helper->delete_transient("duo_auth_".$username."_redirect_url");
        } catch (Exception $e) {
            // there's not much we can do but we shouldn't fail the logout because of this
            $this->duo_debug_log($e->getMessage());
        };
    }

    function clear_current_user_auth()
    {
        $user = $this->wordpress_helper->wp_get_current_user();
        $this->clear_user_auth($user);
    }

    function get_page_url()
    {
        $https_explicitly_enabled = (!empty($_SERVER['HTTPS']) && sanitize_alphanumeric($_SERVER['HTTPS']) != 'off')
        $port = absint($_SERVER['SERVER_PORT'])
        $protocol = ($https_explicitly_enabled || $port == 443) ? "https://" : "http://";
        return sanitize_url($protocol.$_SERVER['HTTP_HOST'].$this->duo_utils->duo_get_uri());
    }

    function exit()
    {
        exit();
    }

    function error_log($str, int $type=0, $destination=null, $headers=null)
    {
        error_log($str, $type, $destination, $headers);
    }

    function duo_start_second_factor($user)
    {
        $this->duo_client->healthCheck();

        $oidc_state = $this->duo_client->generateState();
        $redirect_url = $this->get_page_url();
        $this->duo_client->redirect_url = $redirect_url;
        $this->update_user_auth_status($user->user_login, "in-progress", $redirect_url, $oidc_state);

        $this->wordpress_helper->wp_logout();
        $prompt_uri = $this->duo_client->createAuthUrl($user->user_login, $oidc_state);
        $this->wordpress_helper->wp_redirect($prompt_uri);
        $this->exit();
    }

    function duo_authenticate_user($user="", $username="", $password="")
    {
        // play nicely with other plugins if they have higher priority than us
        if (is_a($user, 'WP_User')) {
            return $user;
        }

        if (!$this->duo_utils->duo_auth_enabled()) {
                $this->duo_debug_log('Duo not enabled, skipping 2FA.');
            return;
        }

        if (isset($_GET['duo_code'])) {
            //secondary auth
            if (isset($_GET["error"])) {
                $error_msg = htmlspecialchars($_GET["error"]) . ":" . htmlspecialchars($_GET["error_description"]);
                $error = $this->wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->wordpress_helper->translate("<strong>ERROR</strong>: $error_msg")
                );
                $this->duo_debug_log($error_msg);
                return $error;
            }

            if (!isset($_GET["state"])) {
                $error_msg = "Missing state";
                $error = $this->wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->wordpress_helper->translate("<strong>ERROR</strong>: $error_msg")
                );
                $this->duo_debug_log($error_msg);
                return $error;
            }
            $this->duo_debug_log('Doing secondary auth');

            // Get authorization token to trade for 2FA
            $code = sanitize_alphanumeric($_GET["duo_code"]);

            // Get state to verify consistency and originality
            $state = sanitize_alphanumeric($_GET["state"]);

            // Retrieve the previously stored state and username from the session
            $associated_user = $this->get_username_from_oidc_state($state);

            if (empty($associated_user)) {
                $error_msg = "No saved state please login again";
                $error = $this->wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->wordpress_helper->translate("<strong>ERROR</strong>: $error_msg")
                );
                $this->duo_debug_log($error_msg);
                return $error;
            }
            try {
                // Update redirect URL to be one associated with initial authentication
                $this->duo_client->redirect_url = $this->get_redirect_url($associated_user);
                $decoded_token = $this->duo_client->exchangeAuthorizationCodeFor2FAResult($code, $associated_user);
            } catch (Duo\DuoUniversal\DuoException $e) {
                $this->duo_debug_log($e->getMessage());
                $error_msg = "Error decoding Duo result. Confirm device clock is correct.";
                $error = $this->wordpress_helper->WP_Error(
                    'Duo authentication failed',
                    $this->wordpress_helper->translate("<strong>ERROR</strong>: $error_msg")
                );
                $this->duo_debug_log($error_msg);
                return $error;
            }
            $this->duo_debug_log("Completed secondary auth for $associated_user");
            $this->update_user_auth_status($associated_user, "authenticated");
            $user = $this->wordpress_helper->WP_User(0, $associated_user);
            return $user;
        }

        if (strlen($username) > 0) {
            // primary auth
            // Don't use get_user_by(). It doesn't return a WP_User object if wordpress version < 3.3
            $user = $this->wordpress_helper->WP_User(0, $username);
            if (!$user) {
                $this->error_log("Failed to retrieve WP user $username");
                return;
            }
            if(!$this->duo_utils->duo_role_require_mfa($user)) {
                $this->duo_debug_log("Skipping 2FA for user: $username with roles: " . print_r($user->roles, true));
                $this->update_user_auth_status($user->user_login, "authenticated");
                return;
            }

            $this->duo_debug_log("Doing primary authentication");
            $this->wordpress_helper->remove_action('authenticate', 'wp_authenticate_username_password', 20);
            $user = $this->wordpress_helper->wp_authenticate_username_password(null, $username, $password);
            if (!is_a($user, 'WP_User')) {
                // on error, return said error (and skip the remaining plugin chain)
                return $user;
            } else {
                $this->duo_debug_log("Primary auth succeeded, starting second factor for $username");
                $this->update_user_auth_status($user->user_login, "in-progress");
                try {
                    $this->duo_start_second_factor($user);
                } catch (Duo\DuoUniversal\DuoException $e) {
                    $this->duo_debug_log($e->getMessage());
                    if ($this->duo_utils->duo_get_option("duo_failmode") == "open") {
                        // If we're failing open, errors in 2FA still allow for success
                        $this->duo_debug_log("Login 'Successful', but 2FA Not Performed. Confirm Duo client/secret/host values are correct");
                        $this->update_user_auth_status($user->user_login, "authenticated");
                        return $user;
                    } else {
                        $error_msg = "2FA Unavailable. Confirm Duo client/secret/host values are correct";
                        $error = $this->wordpress_helper->WP_Error(
                            'Duo authentication_failed',
                            $this->wordpress_helper->translate("<strong>Error</strong>: $error_msg")
                        );
                        $this->duo_debug_log($error_msg);
                        $this->clear_user_auth($user);
                        return $error;
                    }
                }
            }
        }
        $this->duo_debug_log('Starting primary authentication');
    }

    function duo_verify_auth()
    {
        /*
        Verify the user is authenticated with Duo. Start 2FA otherwise
        */
        if (! $this->duo_utils->duo_auth_enabled()) {
            // XXX do we still need this skipping logic?
            if ($this->wordpress_helper->is_multisite()) {
                $site_info = $this->wordpress_helper->get_current_site();
                $this->duo_debug_log("Duo not enabled on " . $site_info->site_name);
            }
            else {
                $this->duo_debug_log('Duo not enabled, skip auth check.');
            }
            return;
        }

        if($this->wordpress_helper->is_user_logged_in()) {
            $user = $this->wordpress_helper->wp_get_current_user();
            $this->duo_debug_log("Verifying auth state for user: $user->user_login");
            if ($this->duo_utils->duo_role_require_mfa($user) && !$this->duo_verify_auth_status($user->user_login)) {
                $this->duo_debug_log("User not authenticated with Duo. Starting second factor for: $user->user_login");
                $this->duo_start_second_factor($user);
            }
            $this->duo_debug_log("User $user->user_login allowed");
        }
    }
}
?>
