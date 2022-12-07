<?php
    function duo_get_roles(){
        global $wp_roles;
        // $wp_roles may not be initially set if wordpress < 3.3
        $wp_roles = isset($wp_roles) ? $wp_roles : new WP_Roles();
        return $wp_roles;
    }

    function duo_auth_enabled(){
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) { 
            duo_debug_log('Found an XMLRPC request. XMLRPC is allowed for this site. Skipping second factor');
            return false; //allows the XML-RPC protocol for remote publishing
        }

        if (duo_get_option('duo_client_id', '') == '' || duo_get_option('duo_client_secret', '') == '' ||
            duo_get_option('duo_host', '') == '') {
            return false;
        }
        return true;
    }

    function duo_role_require_mfa($user){
        $wp_roles = duo_get_roles();
        $all_roles = array();
        foreach ($wp_roles->get_names() as $k=>$r) {
            $all_roles[$k] = $r;
        }

        $duo_roles = duo_get_option('duo_roles', $all_roles); 

        /*
         * WordPress < 3.3 does not include the roles by default
         * Create a User object to get roles info
         * Don't use get_user_by()
         */
        if (!isset($user->roles)){
            $user = new WP_User(0, $user->user_login);
        }

        /*
         * Mainly a workaround for multisite login:
         * if a user logs in to a site different from the one 
         * they are a member of, login will work however
         * it appears as if the user has no roles during authentication
         * "fail closed" in this case and require duo auth
         */
        if(empty($user->roles)) {
            return true;
        }

        foreach ($user->roles as $role) {
            if (array_key_exists($role, $duo_roles)) {
                return true;
            }
        }
        return false;
    }

    function duo_get_uri(){
        // Workaround for IIS which may not set REQUEST_URI, or QUERY parameters
        if (!isset($_SERVER['REQUEST_URI']) ||
            (!empty($_SERVER['QUERY_STRING']) && !strpos($_SERVER['REQUEST_URI'], '?', 0))) {
            $current_uri = substr($_SERVER['PHP_SELF'],1);
            if (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != '') {
                $current_uri .= '?'.$_SERVER['QUERY_STRING'];
            }
            return $current_uri;
        }
        else {
            return $_SERVER['REQUEST_URI'];
        }
    }

    function duo_get_option($key, $default="") {
        if (is_multisite()) {
            return get_site_option($key, $default);
        }
        else {
            return get_option($key, $default);
        }
    }

    function duo_debug_log($message) {
        global $DuoDebug;
        if ($DuoDebug) {
            error_log('Duo debug: ' . $message);
        }
    }

?>
