<?php
    require_once('utilities.php');
    function duo_settings_page() {
        duo_debug_log('Displaying duo setting page');
?>
    <div class="wrap">
        <h2>Duo Two-Factor Authentication</h2>
        <?php if(is_multisite()) { ?>
            <form action="ms-options.php" method="post">
        <?php } else { ?>
            <form action="options.php" method="post"> 
        <?php } ?>
            <?php settings_fields('duo_settings'); ?>
            <?php do_settings_sections('duo_settings'); ?> 
            <p class="submit">
                <input name="Submit" type="submit" class="button primary-button" value="<?php esc_attr_e('Save Changes'); ?>" />
            </p>
        </form>
    </div>
<?php
    }

    function duo_settings_ikey() {
        $ikey = esc_attr(duo_get_option('duo_ikey'));
        echo "<input id='duo_ikey' name='duo_ikey' size='40' type='text' value='$ikey' />";
    }

    function duo_ikey_validate($ikey) {
        if (strlen($ikey) != 20) {
            add_settings_error('duo_ikey', '', 'Integration key is not valid');
            return "";
        } else {
            return $ikey;
        }
    }
    
    function duo_settings_skey() {
        $skey = esc_attr(duo_get_option('duo_skey'));
        echo "<input id='duo_skey' name='duo_skey' size='40' type='password' value='$skey' autocomplete='off' />";
    }

    function duo_skey_validate($skey){
        if (strlen($skey) != 40) {
            add_settings_error('duo_skey', '', 'Secret key is not valid');
            return "";
        } else {
            return $skey;
        }
    }

    function duo_settings_host() {
        $host = esc_attr(duo_get_option('duo_host'));
        echo "<input id='duo_host' name='duo_host' size='40' type='text' value='$host' />";
    }

    function duo_settings_roles() {
        $wp_roles = duo_get_roles();
        $roles = $wp_roles->get_names();
        $newroles = array();
        foreach($roles as $key=>$role) {
            $newroles[before_last_bar($key)] = before_last_bar($role);
        }

        $selected = duo_get_option('duo_roles', $newroles);

        foreach ($wp_roles->get_names() as $key=>$role) {
            //create checkbox for each role
?>
            <input id="duo_roles" name='duo_roles[<?php echo $key; ?>]' type='checkbox' value='<?php echo $role; ?>'  <?php if(in_array($role, $selected)) echo 'checked'; ?> /> <?php echo $role; ?> <br />
<?php
        }
    }

    function duo_roles_validate($options) {
        //return empty array
        if (!is_array($options) || empty($options) || (false === $options)) {
            return array();
        }

        $wp_roles = duo_get_roles();

        $valid_roles = $wp_roles->get_names();
        //otherwise validate each role and then return the array
        foreach ($options as $opt) {
            if (!in_array($opt, $valid_roles)) {
                unset($options[$opt]);
            }
        }
        return $options;
    }

    function duo_settings_text() {
        echo "<p>See the <a target='_blank' href='https://www.duosecurity.com/docs/wordpress'>Duo for WordPress guide</a> to enable Duo two-factor authentication for your WordPress logins.</p>";
        echo '<p>You can retrieve your integration key, secret key, and API hostname by logging in to the Duo Admin Panel.</p>';
        echo '<p>Note: After enabling the plugin, you will be immediately prompted for second factor authentication.</p>';
    }

    function duo_settings_xmlrpc() {
        $val = '';
        if(duo_get_option('duo_xmlrpc', 'off') == 'off') {
            $val = "checked";
        }
        echo "<input id='duo_xmlrpc' name='duo_xmlrpc' type='checkbox' value='off' $val /> Yes<br />";
        echo "Using XML-RPC bypasses two-factor authentication and makes your website less secure. We recommend only using the WordPress web interface for managing your WordPress website.";
    }

    function duo_xmlrpc_validate($option) {
        if($option == 'off') {
            return $option;
        }
        return 'on';
    }

    function duo_add_link($links, $file) {
        static $this_plugin;
        if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);

        if ($file == $this_plugin) {
            $settings_link = '<a href="options-general.php?page=duo_wordpress">'.__("Settings", "duo_wordpress").'</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }


    function duo_add_page() {
        if(! is_multisite()) {
            add_options_page('Duo Two-Factor', 'Duo Two-Factor', 'manage_options', 'duo_wordpress', 'duo_settings_page');
        }
    }


    function duo_add_site_option($option, $value = '') {
        // Add multisite option only if it doesn't exist already
        // With Wordpress versions < 3.3, calling add_site_option will override old values
        if (duo_get_option($option) === FALSE){
            add_site_option($option, $value);
        }
    }


    function duo_admin_init() {
        if (is_multisite()) {
            $wp_roles = duo_get_roles();
            $roles = $wp_roles->get_names();
            $allroles = array();
            foreach($roles as $key=>$role) {
                $allroles[before_last_bar($key)] = before_last_bar($role);
            }
            
            duo_add_site_option('duo_ikey', '');
            duo_add_site_option('duo_skey', '');
            duo_add_site_option('duo_host', '');
            duo_add_site_option('duo_roles', $allroles);
            duo_add_site_option('duo_xmlrpc', 'off');
        }
        else {
            add_settings_section('duo_settings', 'Main Settings', 'duo_settings_text', 'duo_settings');
            add_settings_field('duo_ikey', 'Integration key', 'duo_settings_ikey', 'duo_settings', 'duo_settings');
            add_settings_field('duo_skey', 'Secret key', 'duo_settings_skey', 'duo_settings', 'duo_settings');
            add_settings_field('duo_host', 'API hostname', 'duo_settings_host', 'duo_settings', 'duo_settings');
            add_settings_field('duo_roles', 'Enable for roles:', 'duo_settings_roles', 'duo_settings', 'duo_settings');
            add_settings_field('duo_xmlrpc', 'Disable XML-RPC (recommended)', 'duo_settings_xmlrpc', 'duo_settings', 'duo_settings');
            register_setting('duo_settings', 'duo_ikey', 'duo_ikey_validate');
            register_setting('duo_settings', 'duo_skey', 'duo_skey_validate');
            register_setting('duo_settings', 'duo_host');
            register_setting('duo_settings', 'duo_roles', 'duo_roles_validate');
            register_setting('duo_settings', 'duo_xmlrpc', 'duo_xmlrpc_validate');
        }
    }

    function duo_mu_options() {

?>
        <h3>Duo Security</h3>
        <table class="form-table">
            <?php duo_settings_text();?></td></tr>
            <tr><th>Integration key</th><td><?php duo_settings_ikey();?></td></tr>
            <tr><th>Secret key</th><td><?php duo_settings_skey();?></td></tr>
            <tr><th>API hostname</th><td><?php duo_settings_host();?></td></tr>
            <tr><th>Roles</th><td><?php duo_settings_roles();?></td></tr>
            <tr><th>Disable XML-RPC</th><td><?php duo_settings_xmlrpc();?></td></tr>
        </table>
<?php
    }

    function duo_update_mu_options() {
        if(isset($_POST['duo_ikey'])) {
            $ikey = $_POST['duo_ikey'];
            $result = update_site_option('duo_ikey', $ikey);
        }

        if(isset($_POST['duo_skey'])) {
            $skey = $_POST['duo_skey'];
            $result = update_site_option('duo_skey', $skey);
        }

        if(isset($_POST['duo_host'])) {
            $host = $_POST['duo_host'];
            $result = update_site_option('duo_host', $host);
        }

        if(isset($_POST['duo_roles'])) {
            $roles = $_POST['duo_roles'];
            $result = update_site_option('duo_roles', $roles);
        }
        else {
            $result = update_site_option('duo_roles', []);
        }

        if(isset($_POST['duo_xmlrpc'])) {
            $xmlrpc = $_POST['duo_xmlrpc'];
            $result = update_site_option('duo_xmlrpc', $xmlrpc);
        }
        else {
            $result = update_site_option('duo_xmlrpc', 'on');
        }
    }

    if (!is_multisite()) {
        add_filter('plugin_action_links', 'duo_add_link', 10, 2 );
    }


    //add single-site submenu option
    add_action('admin_menu', 'duo_add_page');
    add_action('admin_init', 'duo_admin_init');

    // Custom fields in network settings
    add_action('wpmu_options', 'duo_mu_options');
    add_action('update_wpmu_options', 'duo_update_mu_options');
?>
