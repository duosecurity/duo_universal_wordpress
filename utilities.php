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

    /*
    * Returns current plugin version.
    *
    * @return string Plugin version
    */
    function duo_get_plugin_version() {
        if (!function_exists('get_plugin_data'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $plugin_data = get_plugin_data( __FILE__ );
        return $plugin_data['Version'];
    }

    function duo_get_user_agent() {
        global $wp_version;
        $duo_wordpress_version = duo_get_plugin_version();
        return $_SERVER['SERVER_SOFTWARE'] . " WordPress/$wp_version duo_wordpress_universal/$duo_wordpress_version";
    }

    /*
     * Get Duo's system time.
     * If that fails then use server system time
     */
    function duo_get_time() {
        $time = NULL;
        if (!extension_loaded('openssl')) {
            //fall back to local time
            error_log('SSL is disabled. Can\'t fetch Duo server time.');
        }
        else {
            global $DuoPing;
            $duo_host = duo_get_option('duo_host');

            // TODO use duo_api_php for fetching duo time
            // Do we need the fopen option?
            $headers = duo_sign_ping($duo_host);
            $duo_url = 'https://' . $duo_host . $DuoPing;
            $cert_file = dirname(__FILE__) . '/duo_web/ca_certs.pem';
            if( ini_get('allow_url_fopen') ) {
                $time =  duo_get_time_fopen($duo_url, $cert_file, $headers);
            } 
            else if(in_array('curl', get_loaded_extensions())){
                $time = duo_get_time_curl($duo_url, $cert_file, $headers);
            }
            else{
                $time = duo_get_time_WP_HTTP($duo_url, $headers);
            }
        }

        //if all fails, use local time
        $time = ($time != NULL ? $time : time());
        return $time;
    }

    // Replaced by duo_api_php
    // function duo_get_time_fopen($duo_url, $cert_file, $headers) 

    // Replaced by duo_api_php
    // function duo_get_time_curl($duo_url, $cert_file, $headers) 

    // TODO do we need this third option?
    // Uses Wordpress HTTP. We can't specify our SSL cert here.
    // Servers with out of date root certs may fail.
    function duo_get_time_WP_HTTP($duo_url, $headers) {
        if(!class_exists('WP_Http')){
            include_once(ABSPATH . WPINC . '/class-http.php');
        }

        $args = array(
            'method'      =>    'GET',
            'blocking'    =>    true,
            'sslverify'   =>    true,
            'user-agent'  =>    duo_get_user_agent(),
            'headers'     =>    $headers,
        );
        $response = wp_remote_get($duo_url, $args);
        if(is_wp_error($response)){
            $error_message = $response->get_error_message();
            error_log("Could not fetch Duo server time: $error_message");
            return NULL;
        }
        else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $time = (int)$body['response']['time'];
            return $time;
        }
    }

    function duo_set_cookie($user){

        global $DuoAuthCookieName;
        global $DuoSecAuthCookieName;
        $ikey_b64 = base64_encode(duo_get_option('duo_ikey'));
        $username_b64 = base64_encode($user->user_login);
        $expire = strtotime('+48 hours');
        //Create http cookie
        $val = base64_encode(sprintf("%s|%s|%s|%s", $DuoAuthCookieName, $username_b64, $ikey_b64, $expire)); 
        $sig = duo_hash_hmac($val);
        $cookie = sprintf("%s|%s", $val, $sig);
        setcookie($DuoAuthCookieName, $cookie, 0, COOKIEPATH, COOKIE_DOMAIN, false, true);
        if (COOKIEPATH != SITECOOKIEPATH){
            setcookie($DuoAuthCookieName, $cookie, 0, SITECOOKIEPATH, COOKIE_DOMAIN, false, true);
        }

        if (is_ssl()){
            //Create https cookie
            $sec_val = base64_encode(sprintf("%s|%s|%s|%s", $DuoSecAuthCookieName, $username_b64, $ikey_b64, $expire)); 
            $sec_sig = duo_hash_hmac($sec_val);
            $sec_cookie = sprintf("%s|%s", $sec_val, $sec_sig);
            setcookie($DuoSecAuthCookieName, $sec_cookie, 0, COOKIEPATH, COOKIE_DOMAIN, true, true);
            if (COOKIEPATH != SITECOOKIEPATH){
                setcookie($DuoSecAuthCookieName, $sec_cookie, 0, SITECOOKIEPATH, COOKIE_DOMAIN, true, true);
            }
        }

        duo_debug_log("Set Duo cookie for user: $user->user_login path: " . COOKIEPATH . " network path: " . SITECOOKIEPATH . " on domain: " . COOKIE_DOMAIN . " set SSL: " . is_ssl());
    }

    //  think about if this should be done with sessions
    //  we can do this with akey and a cookie but the akey would
    //  only be used for hasing the cookie to prevent tampering
    //  we can tie authentication state to the php session but
    //  we would need to make sure that's secure and that we're
    //  appropriately coordinating with wordpress sessions
    function duo_verify_cookie($user){
    /*
        Return true if Duo cookie is valid, false otherwise
        If using SSL, or secure cookie is set, only accept secure cookie
    */
        global $DuoAuthCookieName;
        global $DuoSecAuthCookieName;

        if (is_ssl() || isset($_COOKIE[$DuoSecAuthCookieName])){
            $duo_auth_cookie_name = $DuoSecAuthCookieName;
        }
        else {
            $duo_auth_cookie_name = $DuoAuthCookieName;
        }

        if(!isset($_COOKIE[$duo_auth_cookie_name])){
            error_log("Duo cookie with name: $duo_auth_cookie_name not found. Start two factor authentication. SSL: " . is_ssl());
            return false;
        }

        $cookie_list = explode('|', $_COOKIE[$duo_auth_cookie_name]);
        if (count($cookie_list) !== 2){
            error_log('Invalid Duo cookie');
            return false;
        }
        list($u_cookie_b64, $u_sig) = $cookie_list;
        if (!duo_verify_sig($u_cookie_b64, $u_sig)){
            error_log('Duo cookie signature mismatch');
            return false;
        }

        $cookie_content = explode('|', base64_decode($u_cookie_b64));
        if (count($cookie_content) !== 4){
            error_log('Invalid field count in Duo cookie');
            return false;
        }
        list($cookie_name, $cookie_username_b64, $cookie_ikey_b64, $expire) = $cookie_content;
        // Check cookie values
        if ($cookie_name !== $duo_auth_cookie_name ||
            base64_decode($cookie_username_b64) !== $user->user_login ||
            base64_decode($cookie_ikey_b64) !== duo_get_option('duo_ikey')){
            error_log('Invalid Duo cookie content');
            return false;
        }

        $expire = intval($expire);
        if ($expire < strtotime('now')){
            error_log('Duo cookie expired');
            return false;
        }
        return true;
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

    function duo_hash_hmac($data){
        return hash_hmac('sha1', $data, duo_get_akey());
    }

    // If we don't use a cookie for validation we don't need an akey at all anymore
    function duo_get_akey(){
        // Get an application specific secret key.
        // If wp_salt() is not long enough, append a random secret to it
        $akey = duo_get_option('duo_akey', '');
        $akey .= wp_salt();
        if (strlen($akey) < 40) {
            duo_debug_log('WordPress secret key is less than 40 chars. Creating new akey.');
            $akey = wp_generate_password(40, true, true);
            update_site_option('duo_akey', $akey);
            $akey .= wp_salt();
        }
        return $akey;
    }

?>
