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

    function duo_settings_client_id() {
        $client_id = esc_attr(duo_get_option('duo_client_id'));
        echo "<input id='duo_client_id' name='duo_client_id' size='40' type='text' value='$client_id' />";
    }

    function duo_client_id_validate($client_id) {
        if (strlen($client_id) != 20) {
            add_settings_error('duo_client_id', '', 'Client id is not valid');
            return "";
        } else {
            return $client_id;
        }
    }

    function duo_settings_client_secret() {
        $client_secret = esc_attr(duo_get_option('duo_client_secret'));
        echo "<input id='duo_client_secret' name='duo_client_secret' size='40' type='password' value='$client_secret' autocomplete='off' />";
    }

    function duo_client_secret_validate($client_secret){
        if (strlen($client_secret) != 40) {
            add_settings_error('duo_client_secret', '', 'Client secret is not valid');
            return "";
        } else {
            return $client_secret;
        }
    }

    function duo_settings_host() {
        $host = esc_attr(duo_get_option('duo_host'));
        echo "<input id='duo_host' name='duo_host' size='40' type='text' value='$host' />";
    }

    function duo_settings_failmode() {
        $failmode = esc_attr(duo_get_option('duo_failmode'));
        ?>
            <select id='duo_failmode' name='duo_failmode'/>";
                <option value="open">Open</option>
                <option value="closed">Closed</option>
            </select>
        <?php
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
        echo '<p>You can retrieve your client id, client secret, and API hostname by logging in to the Duo Admin Panel.</p>';
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

            duo_add_site_option('duo_client_id', '');
            duo_add_site_option('duo_client_secret', '');
            duo_add_site_option('duo_host', '');
            duo_add_site_option('duo_failmode', '');
            duo_add_site_option('duo_roles', $allroles);
            duo_add_site_option('duo_xmlrpc', 'off');
        }
        else {
            add_settings_section('duo_settings', 'Main Settings', 'duo_settings_text', 'duo_settings');
            add_settings_field('duo_client_id', 'Client ID', 'duo_settings_client_id', 'duo_settings', 'duo_settings');
            add_settings_field('duo_client_secret', 'Client Secret', 'duo_settings_client_secret', 'duo_settings', 'duo_settings');
            add_settings_field('duo_host', 'API hostname', 'duo_settings_host', 'duo_settings', 'duo_settings');
            add_settings_field('duo_failmode', 'Failmode', 'duo_settings_failmode', 'duo_settings', 'duo_settings');
            add_settings_field('duo_roles', 'Enable for roles:', 'duo_settings_roles', 'duo_settings', 'duo_settings');
            add_settings_field('duo_xmlrpc', 'Disable XML-RPC (recommended)', 'duo_settings_xmlrpc', 'duo_settings', 'duo_settings');
            register_setting('duo_settings', 'duo_client_id', 'duo_client_id_validate');
            register_setting('duo_settings', 'duo_client_secret', 'duo_client_secret_validate');
            register_setting('duo_settings', 'duo_host');
            register_setting('duo_settings', 'duo_failmode');
            register_setting('duo_settings', 'duo_roles', 'duo_roles_validate');
            register_setting('duo_settings', 'duo_xmlrpc', 'duo_xmlrpc_validate');
        }
    }

    function duo_mu_options() {
        duo_debug_log('Displaying multisite settings');

?>
        <h3>Duo Security</h3>
        <table class="form-table">
            <?php duo_settings_text();?></td></tr>
            <tr><th>Client ID</th><td><?php duo_settings_client_id();?></td></tr>
            <tr><th>Client Secret</th><td><?php duo_settings_client_secret();?></td></tr>
            <tr><th>API hostname</th><td><?php duo_settings_host();?></td></tr>
            <tr><th>Failmode</th><td><?php duo_settings_failmode();?></td></tr>
            <tr><th>Roles</th><td><?php duo_settings_roles();?></td></tr>
            <tr><th>Disable XML-RPC</th><td><?php duo_settings_xmlrpc();?></td></tr>
        </table>
<?php
    }

    function duo_update_mu_options() {
        if(isset($_POST['duo_client_id'])) {
            $client_id = $_POST['duo_client_id'];
            $result = update_site_option('duo_client_id', $client_id);
        }

        if(isset($_POST['duo_client_secret'])) {
            $client_secret = $_POST['duo_client_secret'];
            $result = update_site_option('duo_client_secret', $client_secret);
        }

        if(isset($_POST['duo_host'])) {
            $host = $_POST['duo_host'];
            $result = update_site_option('duo_host', $host);
        }

        if(isset($_POST['duo_failmode'])) {
            $failmode = $_POST['duo_failmode'];
            $result = update_site_option('duo_failmode', $failmode);
        } else {
            $result = update_site_option('duo_failmode', "open");
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
