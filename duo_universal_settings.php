<?php
namespace Duo\DuoUniversalWordpress;
require_once('duo_universal_utilities.php');
require_once('duo_universal_wordpress_helper.php');
const SECRET_PLACEHOLDER = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";

class Settings {
    public function __construct(
        $duo_utils
    ) {
        $this->duo_utils = $duo_utils;
        $this->wordpress_helper = $duo_utils->wordpress_helper;
    }
    function duo_settings_page() {
        $this->duo_utils->duo_debug_log('Displaying duo setting page');
?>
    <div class="wrap">
        <h2>Duo Universal Two-Factor Authentication</h2>
        <?php if($this->wordpress_helper->is_multisite()) { ?>
            <form action="ms-options.php" method="post">
        <?php } else { ?>
            <form action="options.php" method="post">
        <?php } ?>
            <?php $this->wordpress_helper->settings_fields('duo_universal_settings'); ?>
            <?php $this->wordpress_helper->do_settings_sections('duo_universal_settings'); ?>
            <p class="submit">
                <input name="Submit" type="submit" class="button primary-button" value="<?php $this->wordpress_helper->esc_attr_e('Save Changes'); ?>" />
            </p>
        </form>
    </div>
<?php
    }

    function duo_settings_client_id() {
        $client_id = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_client_id'));
        echo "<input id='duo_client_id' name='duo_client_id' size='40' type='text' value='$client_id' />";
    }

    function duo_client_id_validate($client_id) {
        $client_id = $this->wordpress_helper->sanitize_text_field($client_id);
        if (strlen($client_id) != 20) {
            $this->wordpress_helper->add_settings_error('duo_client_id', '', 'Client ID is not valid');
            $current_id = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_client_id'));
            if ($current_id) {
                return $current_id;
            }
            return "";
        } else {
            return $client_id;
        }
    }

    function duo_settings_client_secret() {
        $client_secret = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_client_secret'));
        if ($client_secret) {
            $value = SECRET_PLACEHOLDER;
        } else {
            $value = "";
        }
        echo "<input id='duo_client_secret' name='duo_client_secret' size='40' type='password' value='$value' autocomplete='off' />";
    }

    function duo_client_secret_validate($client_secret){
        $client_secret = $this->wordpress_helper->sanitize_text_field($client_secret);
        $current_secret = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_client_secret'));
        if (strlen($client_secret) != 40) {
            $this->wordpress_helper->add_settings_error('duo_client_secret', '', 'Client secret is not valid');
            if ($current_secret) {
                return $current_secret;
            } else {
                return "";
            }
        } else {
            if ($client_secret == SECRET_PLACEHOLDER) {
                return $current_secret;
            } else {
                return $client_secret;
            }
        }
    }

    function duo_settings_host() {
        $host = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_host'));
        echo "<input id='duo_host' name='duo_host' size='40' type='text' value='$host' />";
    }

    function duo_host_validate($host) {
        $host = $this->wordpress_helper->sanitize_text_field($host);
        if (!preg_match('/^api-[a-zA-Z\d\.-]*/', $host) or str_starts_with($host, 'api-api-')) {
            $this->wordpress_helper->add_settings_error('duo_host', '', 'Host is not valid');
            $current_host = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_host'));
            if ($current_host) {
                return $current_host;
            }
            return "";
        }

        return $host;
    }

    function duo_settings_failmode() {
        $failmode = $this->wordpress_helper->esc_attr($this->duo_utils->duo_get_option('duo_failmode', 'open'));
        echo '<select id="duo_failmode" name="duo_failmode" />';
        if ($failmode == 'open')
        {
            echo '<option value="open" selected>Open</option>';
            echo '<option value="closed">Closed</option';
        }
        else
        {
            echo '<option value="open">Open</option>';
            echo '<option value="closed" selected>Closed</option';
        }
        echo '</select>';
    }

    function duo_failmode_validate($failmode) {
        $failmode = $this->wordpress_helper->sanitize_text_field($failmode);
        if (!in_array($failmode, array('open', 'closed'))) {
            $this->wordpress_helper->add_settings_error('duo_failmode', '', 'Failmode value is not valid');
            $current_failmode = $this->duo_utils->duo_get_option('duo_failmode', 'open');
            return $current_failmode;
        }
        return $failmode;
    }

    function duo_settings_roles() {
        $wp_roles = $this->duo_utils->duo_get_roles();
        $roles = $wp_roles->get_names();
        $newroles = array();
        foreach($roles as $key=>$role) {
            $newroles[$this->wordpress_helper->before_last_bar($key)] = $this->wordpress_helper->before_last_bar($role);
        }

        $selected = $this->duo_utils->duo_get_option('duo_roles', $newroles);

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
        $wp_roles = $this->duo_utils->duo_get_roles();

        $valid_roles = $wp_roles->get_names();
        //otherwise validate each role and then return the array
        foreach ($options as $opt=>$value) {
            if (!array_key_exists($opt, $valid_roles)) {
                unset($options[$opt]);
            } else {
                $options[$opt] = $this->wordpress_helper->sanitize_text_field($value);
            }
        }
        return $options;
    }

    function duo_settings_text() {
        echo "<p>See the <a target='_blank' href='https://www.duosecurity.com/docs/wordpress'>Duo for WordPress guide</a> to enable Duo two-factor authentication for your WordPress logins.</p>";
        echo '<p>You can retrieve your Client ID, Client Secret, and API hostname by logging in to the Duo Admin Panel.</p>';
        echo '<p>Note: After enabling the plugin, you will be immediately prompted for second factor authentication.</p>';
    }

    function duo_settings_xmlrpc() {
        $val = '';
        if($this->duo_utils->duo_get_option('duo_xmlrpc', 'off') == 'off') {
            $val = "checked";
        }
        echo "<input id='duo_xmlrpc' name='duo_xmlrpc' type='checkbox' value='off' $val /> Yes<br />";
        echo "Using XML-RPC bypasses two-factor authentication and makes your website less secure. We recommend only using the WordPress web interface for managing your WordPress website.";
    }

    function duo_xmlrpc_validate($option) {
        $option = $this->wordpress_helper->sanitize_text_field($option);
        if($option == 'off') {
            return $option;
        }
        return 'on';
    }

    function duo_add_link($links, $file) {
        $settings_link = '<a href="options-general.php?page=duo_universal_wordpress">'.$this->wordpress_helper->translate("Settings", "duo_universal_wordpress").'</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    function duo_add_page() {
        if(! $this->wordpress_helper->is_multisite()) {
            $this->wordpress_helper->add_options_page('Duo Universal Two-Factor', 'Duo Universal Two-Factor', 'manage_options', 'duo_universal_wordpress', array($this, 'duo_settings_page'));
        }
    }


    function duo_add_site_option($option, $value = '') {
        // Add multisite option only if it doesn't exist already
        // With Wordpress versions < 3.3, calling add_site_option will override old values
        if ($this->duo_utils->duo_get_option($option) === FALSE){
            $this->wordpress_helper->add_site_option($option, $value);
        }
    }


    function duo_admin_init() {
        if ($this->wordpress_helper->is_multisite()) {
            $wp_roles = $this->duo_utils->duo_get_roles();
            $roles = $wp_roles->get_names();
            $allroles = array();
            foreach($roles as $key=>$role) {
                $allroles[$this->wordpress_helper->before_last_bar($key)] = $this->wordpress_helper->before_last_bar($role);
            }

            $this->duo_add_site_option('duo_client_id', '');
            $this->duo_add_site_option('duo_client_secret', '');
            $this->duo_add_site_option('duo_host', '');
            $this->duo_add_site_option('duo_failmode', '');
            $this->duo_add_site_option('duo_roles', $allroles);
            $this->duo_add_site_option('duo_xmlrpc', 'off');
        }
        else {
            $this->wordpress_helper->add_settings_section('duo_universal_settings', 'Main Settings', array($this, 'duo_settings_text'), 'duo_universal_settings');

            $this->wordpress_helper->add_settings_field('duo_client_id', 'Client ID', array($this, 'duo_settings_client_id'), 'duo_universal_settings', 'duo_universal_settings');
            $this->wordpress_helper->add_settings_field('duo_client_secret', 'Client Secret', array($this, 'duo_settings_client_secret'), 'duo_universal_settings', 'duo_universal_settings');
            $this->wordpress_helper->add_settings_field('duo_host', 'API hostname', array($this, 'duo_settings_host'), 'duo_universal_settings', 'duo_universal_settings');
            $this->wordpress_helper->add_settings_field('duo_failmode', 'Failmode', array($this, 'duo_settings_failmode'), 'duo_universal_settings', 'duo_universal_settings');
            $this->wordpress_helper->add_settings_field('duo_roles', 'Enable for roles:', array($this, 'duo_settings_roles'), 'duo_universal_settings', 'duo_universal_settings');
            $this->wordpress_helper->add_settings_field('duo_xmlrpc', 'Disable XML-RPC (recommended)', array($this, 'duo_settings_xmlrpc'), 'duo_universal_settings', 'duo_universal_settings');

            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_client_id', array($this, 'duo_client_id_validate'));
            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_client_secret', array($this, 'duo_client_secret_validate'));
            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_host', array($this, 'duo_host_validate'));
            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_failmode', array($this, 'duo_failmode_validate'));
            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_roles', array($this, 'duo_roles_validate'));
            $this->wordpress_helper->register_setting('duo_universal_settings', 'duo_xmlrpc', array($this, 'duo_xmlrpc_validate'));
        }
    }

    function duo_mu_options() {
        $this->duo_utils->duo_debug_log('Displaying multisite settings');

?>
        <h3>Duo Security</h3>
        <table class="form-table">
            <?php $this->duo_settings_text();?></td></tr>
            <tr><th>Client ID</th><td><?php $this->duo_settings_client_id();?></td></tr>
            <tr><th>Client Secret</th><td><?php $this->duo_settings_client_secret();?></td></tr>
            <tr><th>API hostname</th><td><?php $this->duo_settings_host();?></td></tr>
            <tr><th>Failmode</th><td><?php $this->duo_settings_failmode();?></td></tr>
            <tr><th>Roles</th><td><?php $this->duo_settings_roles();?></td></tr>
            <tr><th>Disable XML-RPC</th><td><?php $this->duo_settings_xmlrpc();?></td></tr>
        </table>
<?php
    }

    function duo_update_mu_options() {
        if(isset($_POST['duo_client_id'])) {
            $client_id = $this->duo_client_id_validate($_POST['duo_client_id']);
            $result = $this->wordpress_helper->update_site_option('duo_client_id', $client_id);
        }

        if(isset($_POST['duo_client_secret'])) {
            $client_secret = $this->duo_client_secret_validate($_POST['duo_client_secret']);
            $result = $this->wordpress_helper->update_site_option('duo_client_secret', $client_secret);
        }

        if(isset($_POST['duo_host'])) {
            $host = $this->duo_host_validate($_POST['duo_host']);
            $result = $this->wordpress_helper->update_site_option('duo_host', $host);
        }

        if(isset($_POST['duo_failmode'])) {
            $failmode = $this->duo_failmode_validate($_POST['duo_failmode']);
            $result = $this->wordpress_helper->update_site_option('duo_failmode', $failmode);
        } else {
            $result = $this->wordpress_helper->update_site_option('duo_failmode', "open");
        }

        if(isset($_POST['duo_roles'])) {
            $roles = $this->duo_roles_validate($_POST['duo_roles']);
            $result = $this->wordpress_helper->update_site_option('duo_roles', $roles);
        }
        else {
            $result = $this->wordpress_helper->update_site_option('duo_roles', []);
        }

        if(isset($_POST['duo_xmlrpc'])) {
            $xmlrpc = $this->duo_xmlrpc_validate($_POST['duo_xmlrpc']);
            $result = $this->wordpress_helper->update_site_option('duo_xmlrpc', $xmlrpc);
        }
        else {
            $result = $this->wordpress_helper->update_site_option('duo_xmlrpc', 'on');
        }
    }
}
?>
