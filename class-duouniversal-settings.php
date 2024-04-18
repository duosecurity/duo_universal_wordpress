<?php
/**
 * Handle settings for plugin
 *
 * This class handles the sanitization, validation and display
 * of the various settings associated with this plugin.
 *
 * @link https://duo.com/docs/wordpress
 *
 * @package Duo Universal
 * @since 1.0.0
 */

namespace Duo\DuoUniversalWordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . 'class-duouniversal-utilities.php';
const SECRET_PLACEHOLDER = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

class DuoUniversal_Settings {
	public $duo_utils;

	public function __construct(
		$duo_utils
	) {
		$this->duo_utils = $duo_utils;
	}
	public function duo_settings_page() {
		$this->duo_utils->duo_debug_log( 'Displaying duo setting page' );
		?>
	<div class="wrap">
		<h2>Duo Universal Authentication</h2>
		<?php if ( is_multisite() ) { ?>
			<form action="ms-options.php" method="post">
		<?php } else { ?>
			<form action="options.php" method="post">
		<?php } ?>
			<?php \settings_fields( 'duo_universal_settings' ); ?>
			<?php \do_settings_sections( 'duo_universal_settings' ); ?>
			<p class="submit">
				<input name="Submit" type="submit" class="button primary-button" value="<?php \esc_attr_e( 'Save Changes', 'duo-universal' ); ?>" />
			</p>
		</form>
	</div>
		<?php
	}

	public function duo_settings_client_id() {
		$client_id = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_id' ) );
		return "<input id='duoup_client_id' name='duoup_client_id' size='40' type='text' value='" . \esc_attr( $client_id ) . "' />";
	}

	public function duoup_client_id_validate( $client_id ) {
		$client_id = sanitize_text_field( $client_id );
		if ( strlen( $client_id ) !== 20 ) {
			\add_settings_error( 'duoup_client_id', '', __( 'Client ID is not valid', 'duo-universal' ) );
			$current_id = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_id' ) );
			if ( $current_id ) {
				return $current_id;
			}
			return '';
		} else {
			return $client_id;
		}
	}

	public function duo_settings_client_secret() {
		$client_secret = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_secret' ) );
		if ( $client_secret ) {
			$value = SECRET_PLACEHOLDER;
		} else {
			$value = '';
		}
		return "<input id='duoup_client_secret' name='duoup_client_secret' size='40' type='password' value='" . \esc_attr( $value ) . "' autocomplete='off' />";
	}

	public function duoup_client_secret_validate( $client_secret ) {
		$client_secret  = sanitize_text_field( $client_secret );
		$current_secret = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_client_secret' ) );
		if ( strlen( $client_secret ) !== 40 ) {
			\add_settings_error( 'duoup_client_secret', '', __( 'Client secret is not valid', 'duo-universal' ) );
			if ( $current_secret ) {
				return $current_secret;
			} else {
				return '';
			}
		} elseif ( SECRET_PLACEHOLDER === $client_secret ) {
				return $current_secret;
		} else {
			return $client_secret;
		}
	}

	public function duo_settings_host() {
		$host = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_api_host' ) );
		return "<input id='duoup_api_host' name='duoup_api_host' size='40' type='text' value='" . \esc_attr( $host ) . "' />";
	}

	public function duoup_api_host_validate( $host ) {
		$host = sanitize_text_field( $host );
		if ( ! preg_match( '/^api-[a-zA-Z\d\.-]*/', $host ) || str_starts_with( $host, 'api-api-' ) ) {
			\add_settings_error( 'duoup_api_host', '', __( 'Host is not valid', 'duo-universal' ) );
			$current_host = \esc_attr( $this->duo_utils->duo_get_option( 'duo_host' ) );
			if ( $current_host ) {
				return $current_host;
			}
			return '';
		}

		return $host;
	}

	public function duo_settings_failmode() {
		$failmode = \esc_attr( $this->duo_utils->duo_get_option( 'duoup_failmode', 'open' ) );
		$result   = '';
		$result  .= '<select id="duoup_failmode" name="duoup_failmode" />';
		if ( 'open' === $failmode ) {
			$result .= sprintf( '<option value="open" selected>%s</option>', \esc_html__( 'Open', 'duo-universal' ) );
			$result .= sprintf( '<option value="closed">%s</option>', \esc_html__( 'Closed', 'duo-universal' ) );
		} else {
			$result .= sprintf( '<option value="open">%s</option>', \esc_html__( 'Open', 'duo-universal' ) );
			$result .= sprintf( '<option value="closed" selected>%s</option>', \esc_html__( 'Closed', 'duo-universal' ) );
		}
		$result .= '</select>';
		return $result;
	}

	public function duoup_failmode_validate( $failmode ) {
		$failmode = sanitize_text_field( $failmode );
		if ( ! in_array( $failmode, array( 'open', 'closed' ), true ) ) {
			add_settings_error( 'duoup_failmode', '', __( 'Failmode value is not valid', 'duo-universal' ) );
			$current_failmode = $this->duo_utils->duo_get_option( 'duoup_failmode', 'open' );
			return $current_failmode;
		}
		return $failmode;
	}

	public function duo_settings_roles() {
		$wp_roles = $this->duo_utils->duo_get_roles();
		$roles    = $wp_roles->get_names();
		$newroles = array();
		foreach ( $roles as $key => $role ) {
			$newroles[ \before_last_bar( $key ) ] = \before_last_bar( $role );
		}

		$selected = $this->duo_utils->duo_get_option( 'duoup_roles', $newroles );

		$result = '';
		foreach ( $wp_roles->get_names() as $key => $role ) {
			// create checkbox for each role
			$result .= sprintf(
				( '' .
				"<input id='duoup_roles' " .
				"name='duoup_roles[%s]' " .
				"type='checkbox' " .
				"value='%s' " .
				'%s' .
				'/>' .
				'%s' .
				'<br />' ),
				\esc_attr( $key ),
				\esc_attr( $role ),
				// we have to use checked=true here because wp_kses doesn't
				// handle boolean attributes
				in_array( $role, $selected, true ) ? 'checked=true' : '',
				\esc_html( $role )
			);
		}
		return $result;
	}

	public function duoup_roles_validate( $options ) {
		// return empty array.
		if ( ! is_array( $options ) || empty( $options ) || ( false === $options ) ) {
			return array();
		}
		$wp_roles = $this->duo_utils->duo_get_roles();

		$valid_roles = $wp_roles->get_names();
		// otherwise validate each role and then return the array.
		foreach ( $options as $opt => $value ) {
			if ( ! array_key_exists( $opt, $valid_roles ) ) {
				unset( $options[ $opt ] );
			} else {
				$options[ $opt ] = sanitize_text_field( $value );
			}
		}
		return $options;
	}

	public function duo_settings_text() {
		printf( '<p>%s</p>', \esc_html__( 'To use this plugin you must have an account with Duo Security.', 'duo-universal' ) );
		printf( '<p>%s</p>', \esc_html__( 'See the Duo for WordPress guide to enable Duo two-factor authentication for your WordPress logins.', 'duo-universal' ) );
		printf( "<a target='_blank' href='https://www.duosecurity.com/docs/wordpress'>%s</a>", \esc_html__( 'Duo for WordPress guide', 'duo-universal' ) );
		printf( '<p>%s</p>', \esc_html__( 'You can retrieve your Client ID, Client Secret, and API hostname by logging in to the Duo Admin Panel.', 'duo-universal' ) );
		printf( '<p>%s</p>', \esc_html__( 'Note: After enabling the plugin, you will be immediately prompted for second factor authentication.', 'duo-universal' ) );
	}

	public function duo_settings_xmlrpc() {
		$val = '';
		if ( $this->duo_utils->duo_get_option( 'duoup_xmlrpc', 'off' ) === 'off' ) {
			// we have to use checked=true here because wp_kses doesn't
			// handle boolean attributes
			$val = 'checked=true';
		}
		$result  = sprintf( "<input id='duoup_xmlrpc' name='duoup_xmlrpc' type='checkbox' value='off' %s /> %s<br />", \esc_attr( $val ), \esc_html__( 'Yes', 'duo-universal' ) );
		$result .= \esc_html__( 'Using XML-RPC bypasses two-factor authentication and makes your website less secure. We recommend only using the WordPress web interface for managing your WordPress website.', 'duo-universal' );
		return $result;
	}

	public function duoup_xmlrpc_validate( $option ) {
		$option = sanitize_text_field( $option );
		if ( 'off' === $option ) {
			return $option;
		}
		return 'on';
	}

	public function duo_add_link( $links ) {
		$settings_link = sprintf( '<a href="options-general.php?page=duo_universal">%s</a>', \esc_html__( 'Settings', 'duo-universal' ) );
		array_unshift( $links, $settings_link );
		return $links;
	}


	public function duo_add_page() {
		if ( ! is_multisite() ) {
			add_options_page(
				__( 'Duo Universal', 'duo-universal' ),
				__( 'Duo Universal', 'duo-universal' ),
				'manage_options',
				'duo_universal',
				array( $this, 'duo_settings_page' )
			);
		}
	}


	public function duo_add_site_option( $option, $value = '' ) {
		// Add multisite option only if it doesn't exist already
		// With WordPress versions < 3.3, calling add_site_option will override old values.
		if ( $this->duo_utils->duo_get_option( $option ) === false ) {
			\add_site_option( $option, $value );
		}
	}

	public function duoup_add_settings_field( $id, $title, $callback, $sanitize_callback, $text ) {
		\add_settings_field(
			$id,
			$title,
			$callback,
			'duo_universal_settings',
			'duo_universal_settings',
			array(
				'text'      => $text,
				'label_for' => $id,
			)
		);
		\register_setting( 'duo_universal_settings', $id, $sanitize_callback );
	}

	public function printing_callback( $text ) {
		echo(
			\wp_kses(
				$text['text'],
				array(
					'input'  => array(
						'id'           => array(),
						'name'         => array(),
						'size'         => array(),
						'type'         => array(),
						'value'        => array(),
						'autocomplete' => array(),
						'checked'      => array(),
					),
					'select' => array(
						'id'   => array(),
						'name' => array(),
					),
					'option' => array(
						'value'    => array(),
						'selected' => array(),
					),
					'br'     => array(),
				),
			)
		);
	}

	public function duo_admin_init() {
		if ( is_multisite() ) {
			$wp_roles = $this->duo_utils->duo_get_roles();
			$roles    = $wp_roles->get_names();
			$allroles = array();
			foreach ( $roles as $key => $role ) {
				$allroles[ \before_last_bar( $key ) ] = \before_last_bar( $role );
			}

			$this->duo_add_site_option( 'duoup_client_id', '' );
			$this->duo_add_site_option( 'duoup_client_secret', '' );
			$this->duo_add_site_option( 'duoup_api_host', '' );
			$this->duo_add_site_option( 'duoup_failmode', '' );
			$this->duo_add_site_option( 'duoup_roles', $allroles );
			$this->duo_add_site_option( 'duoup_xmlrpc', 'off' );
		} else {
			\add_settings_section( 'duo_universal_settings', __( 'Main Settings', 'duo-universal' ), array( $this, 'duo_settings_text' ), 'duo_universal_settings' );
			$this->duoup_add_settings_field( 'duoup_client_id', __( 'Client ID', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_client_id_validate' ), $this->duo_settings_client_id() );
			$this->duoup_add_settings_field( 'duoup_client_secret', __( 'Client Secret', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_client_secret_validate' ), $this->duo_settings_client_secret() );
			$this->duoup_add_settings_field( 'duoup_api_host', __( 'API hostname', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_api_host_validate' ), $this->duo_settings_host() );
			$this->duoup_add_settings_field( 'duoup_failmode', __( 'Failmode', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_failmode_validate' ), $this->duo_settings_failmode() );
			$this->duoup_add_settings_field( 'duoup_roles', __( 'Enable for roles:', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_roles_validate' ), $this->duo_settings_roles() );
			$this->duoup_add_settings_field( 'duoup_xmlrpc', __( 'Disable XML-RPC (recommended)', 'duo-universal' ), array( $this, 'printing_callback' ), array( $this, 'duoup_xmlrpc_validate' ), $this->duo_settings_xmlrpc() );
		}
	}

	public function print_field( $id, $label, $input ) {
		printf(
			"<tr><th><label for='%s'>%s</label></th><td>%s</td></tr>\n",
			\esc_attr( $id ),
			\esc_html( $label ),
			\wp_kses(
				$input,
				array(
					'input'  => array(
						'id'           => array(),
						'name'         => array(),
						'size'         => array(),
						'type'         => array(),
						'value'        => array(),
						'autocomplete' => array(),
						'checked'      => array(),
					),
					'select' => array(
						'id'   => array(),
						'name' => array(),
					),
					'option' => array(
						'value'    => array(),
						'selected' => array(),
					),
					'br'     => array(),
				),
			)
		);
	}

	public function duo_mu_options() {
		$this->duo_utils->duo_debug_log( 'Displaying multisite settings' );

		printf( "<h3>%s</h3>\n", \esc_html__( 'Duo Security', 'duo-universal' ) );
		echo( "<table class='form-table'>\n" );
			$this->duo_settings_text();
			printf( "</td></tr>\n" );
			$this->print_field( 'duoup_client_id', \__( 'Client ID', 'duo-universal' ), $this->duo_settings_client_id() );
			$this->print_field( 'duoup_client_secret', \__( 'Client Secret', 'duo-universal' ), $this->duo_settings_client_secret() );
			$this->print_field( 'duoup_api_host', \__( 'API hostname', 'duo-universal' ), $this->duo_settings_host() );
			$this->print_field( 'duoup_failmode', \__( 'Failmode', 'duo-universal' ), $this->duo_settings_failmode() );
			$this->print_field( 'duoup_roles', \__( 'Roles', 'duo-universal' ), $this->duo_settings_roles() );
			$this->print_field( 'duoup_xmlrpc', \__( 'Disable XML-RPC (recommended)', 'duo-universal' ), $this->duo_settings_xmlrpc() );
		echo( "</table>\n" );
	}

	public function duo_update_mu_options() {
		check_admin_referer( 'siteoptions' );

		if ( isset( $_POST['duoup_client_id'] ) ) {
			$client_id = $this->duoup_client_id_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_client_id'] ) ) );
			$result    = \update_site_option( 'duoup_client_id', $client_id );
		}

		if ( isset( $_POST['duoup_client_secret'] ) ) {
			$client_secret = $this->duoup_client_secret_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_client_secret'] ) ) );
			$result        = \update_site_option( 'duoup_client_secret', $client_secret );
		}

		if ( isset( $_POST['duoup_api_host'] ) ) {
			$host   = $this->duoup_api_host_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_api_host'] ) ) );
			$result = \update_site_option( 'duoup_api_host', $host );
		}

		if ( isset( $_POST['duoup_failmode'] ) ) {
			$failmode = $this->duoup_failmode_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_failmode'] ) ) );
			$result   = \update_site_option( 'duoup_failmode', $failmode );
		} else {
			$result = \update_site_option( 'duoup_failmode', 'open' );
		}

		if ( isset( $_POST['duoup_roles'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$roles  = $this->duoup_roles_validate( \wp_unslash( $_POST['duoup_roles'] ) );
			$result = \update_site_option( 'duoup_roles', $roles );
		} else {
			$result = \update_site_option( 'duoup_roles', array() );
		}

		if ( isset( $_POST['duoup_xmlrpc'] ) ) {
			$xmlrpc = $this->duoup_xmlrpc_validate( sanitize_text_field( \wp_unslash( $_POST['duoup_xmlrpc'] ) ) );
			$result = \update_site_option( 'duoup_xmlrpc', $xmlrpc );
		} else {
			$result = \update_site_option( 'duoup_xmlrpc', 'on' );
		}
	}
}
