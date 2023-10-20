<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once 'duo_universal_settings.php';
require_once 'duo_universal_wordpress_helper.php';

final class SettingsTest extends TestCase
{

    function setUp(): void
    {
        $this->duo_client = $this->createMock(Duo\DuoUniversal\Client::class);
        $this->helper = $this->createMock(Duo\DuoUniversalWordpress\WordpressHelper::class);
        // For filtering and sanitization methods provided by wordpress,
        // simply return the value passed in for filtering unchanged since we
        // don't have the wordpress methods in scope
        $this->helper->method('apply_filters')->willReturnArgument(1);
        $this->helper->method('sanitize_url')->willReturnArgument(0);
        $this->helper->method('sanitize_text_field')->willReturnArgument(0);
        $this->duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $this->duo_utils->wordpress_helper = $this->helper;
    }

    /**
     * Test that settings page loads correctly
     * when multisite is enabled
     */
    public function testSettingsPageMultisite(): void
    {
        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->method('settings_fields')->willReturn(null);
        $this->helper->method('do_settings_sections')->willReturn(null);
        $this->helper->method('esc_attr_e')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_settings_page();
        $this->expectOutputRegex('/ms-options/');
    }

    /**
     * Test that settings page loads correctly
     * when multisite is disabled
     */
    public function testSettingsPageSingleSite(): void
    {
        $this->helper->method('is_multisite')->willReturn(false);
        $this->helper->method('settings_fields')->willReturn(null);
        $this->helper->method('do_settings_sections')->willReturn(null);
        $this->helper->method('esc_attr_e')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_settings_page();
        $this->expectOutputRegex('/action="options/');
    }

    /**
     * Test that client id shows up in the output
     */
    public function testSettingsClientID(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-value");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $settings->duo_settings_client_id();
        $this->expectOutputRegex('/this-is-a-test-value/');
    }

    /**
     * Test that an invalid client id yields the empty string
     */
    public function testDuoClientIDValidateInvalid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $result = $settings->duo_client_id_validate("invalid id");

        $this->assertEquals($result, "");
    }

    /**
     * Test that an invalid client id doesn't clear exist value
     */
    public function testDuoClientIDValidateInvalidNoClear(): void
    {
        $id = "this is an id";
        $this->helper->method('add_settings_error')->willReturn(null);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $this->duo_utils->method('duo_get_option')->willReturn($id);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $result = $settings->duo_client_id_validate("invalid id");

        $this->assertEquals($id, $result);
    }

    /**
     * Test that a valid client id validates
     */
    public function testDuoClientIDValidateValid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $client_id = "DIXXXXXXXXXXXXXXXXXX";

        $result = $settings->duo_client_id_validate($client_id);

        $this->assertEquals($result, $client_id);
    }

    /**
     * Test that client secret doesn't show up in the output if set
     */
    public function testSettingsClientSecret(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-fake-secret");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $settings->duo_settings_client_secret();
        $this->expectOutputRegex("/".Duo\DuoUniversalWordpress\SECRET_PLACEHOLDER."/");
    }

    /**
     * Test that an invalid client secret yields the empty string
     */
    public function testDuoClientSecretValidateInvalid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $this->helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $result = $settings->duo_client_secret_validate("invalid secret");

        $this->assertEquals($result, "");
    }

    /**
     * Test that the dummy secret doesn't overwrite settings
     */
    public function testDuoClientSecretValidateDummyDoesntSave(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['add_settings_error', 'esc_attr'])
            ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $duo_utils->method('duo_get_option')->willReturn("current secret that is 40 character long");

        $result = $settings->duo_client_secret_validate(Duo\DuoUniversalWordpress\SECRET_PLACEHOLDER);

        $this->assertEquals($result, "current secret that is 40 character long");
    }

    /**
     * Test that a valid client secret validates
     */
    public function testDuoClientSecretValidateValid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $this->helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $client_secret = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";

        $result = $settings->duo_client_secret_validate($client_secret);

        $this->assertEquals($result, $client_secret);
    }

    /**
     * Test that an invalid client secret doesn't clear current secret
     */
    public function testDuoClientSecretValidateInvalidNoClear(): void
    {
        $original_secret = "current secret that is 40 character long";
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['add_settings_error', 'esc_attr'])
            ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->method('duo_get_option')->willReturn($original_secret);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $client_secret = "bad secret";

        $result = $settings->duo_client_secret_validate($client_secret);

        $this->assertEquals($result, $original_secret);
    }

    /**
     * Test that the host shows up in output
     */
    public function testSettingsHostOutput(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-host");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $settings->duo_settings_host();

        $this->expectOutputRegex('/this-is-a-test-host/');
    }

    /**
     * Test that the failmode shows up in output
     * with correct mode selected
     */
    public function testSettingsFailmode(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("closed");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $this->expectOutputRegex('/"closed" selected/');
        $settings->duo_settings_failmode();
    }

    /**
     * Test that selected roles show up as checked
     * when generating role HTML
     */
    public function testSettingsRoles(): void
    {
        $duo_roles = array(
            "uses_2fa" => true,
            "skip_2fa" => false
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);
        $this->helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option', 'duo_get_roles'])
            ->getMock();

        $duo_utils->method('duo_get_option')->willReturn(["uses_2fa"]);
        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $settings->duo_settings_roles();
        $output = $this->getActualOutput();

        $this->assertEquals(
            1, preg_match(
                "/name='duo_roles\[uses_2fa\]' type='checkbox' value='1'  checked/",
                $output
            )
        );
        $this->assertEquals(
            1, preg_match(
                "/name='duo_roles\[skip_2fa\]' type='checkbox' value=''/",
                $output
            )
        );
    }

    /**
     * Test that disable XMLRPC settings box is checked when
     * setting is set to off
     */
    public function testDuoSettingsXMLRPC(): void
    {
        $helper = $this->createStub(stdClass::class);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();

        $duo_utils->method('duo_get_option')->willReturn('off');
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $result = $settings->duo_settings_xmlrpc();
        $output = $this->getActualOutput();

        $this->expectOutputRegex('/checked/');
    }

    /**
     * Test that duo_role_validate returns the list
     * of options if all are valid
     */
    public function testDuoRolesValidateGood(): void
    {
        $duo_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['before_last_bar'])
            ->getMock();
        $helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($helper))
            ->onlyMethods(['duo_get_roles'])
            ->getMock();

        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $input = array(
            "Editor" => "role"
        );

        $result = $settings->duo_roles_validate($input);

        $this->assertEquals($result, $input);
    }

    /**
     * Test that duo_role_validate returns the empty
     * array if falsey options are passed
     */
    public function testDuoRolesValidateEmpty(): void
    {
        $this->duo_utils->wordpress_helper = null;
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $this->assertEmpty($settings->duo_roles_validate(1));
        $this->assertEmpty($settings->duo_roles_validate(array()));
        $this->assertEmpty($settings->duo_roles_validate(false));
    }

    /**
     * Test that duo_role_validate removes bad options
     * from selected role array
     */
    public function testDuoRolesValidateBadOptionsAreRemoved(): void
    {
        $duo_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['before_last_bar'])
            ->getMock();
        $helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($helper))
            ->onlyMethods(['duo_get_roles'])
            ->getMock();

        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $result = $settings->duo_roles_validate(
            array(
            "Missing" => "role",
            )
        );

        $this->assertEquals($result, array());
    }

    /**
     * Test that duo_add_page NOT being called in multisite
     */
    public function testDuoAddPageSingleSite(): void
    {
        $this->helper->method('is_multisite')->willReturn(false);
        $this->helper->method('add_options_page');
        $this->helper->expects($this->once())->method('add_options_page');
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_add_page();
    }

    /**
     * Test that duo_add_page being called in multisite
     */
    public function testDuoAddPageMultisite(): void
    {
        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->expects($this->never())->method('add_options_page');
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_add_page();
    }

    /**
     * Test that duo_add_site_option not add already exist options
     */
    public function testDuoAddDuplicateSiteOption(): void
    {
        $this->duo_utils->method('duo_get_option')->willReturn(true);
        $this->helper->expects($this->never())->method('add_site_option');
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_add_site_option("FakeOption");
    }

    /**
     * Test that duo_add_site_option add option to non-exist option
     */
    public function testDuoAddNewSiteOption(): void
    {
        $this->duo_utils->method('duo_get_option')->willReturn(false);
        $this->helper
            ->expects($this->once())
            ->method('add_site_option')
            ->with("FakeOption");
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);

        $settings->duo_add_site_option("FakeOption");
    }

    /**
     * Test that duo_admin_init add correct options for multisite
     */
    public function testDuoAdminInitForMultisite(): void
    {
        $duo_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);
        $this->duo_utils->method('duo_get_roles')->willReturn($roles);

        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->method('before_last_bar')->will($this->returnArgument(0));

        $settings = $this->getMockBuilder(Duo\DuoUniversalWordpress\Settings::class)
            ->setConstructorArgs(array($this->duo_utils))
            ->onlyMethods(['duo_add_site_option'])
            ->getMock();

        $settings->expects($this->exactly(6))
            ->method('duo_add_site_option')
            ->withConsecutive(
                ['duo_client_id', ''],
                ['duo_client_secret', ''],
                ['duo_host', ''],
                ['duo_failmode', ''],
                ['duo_roles', $duo_roles],
                ['duo_xmlrpc', 'off'],
            );

        $settings->duo_admin_init();
    }

    /**
     * Test that duo_admin_init add correct options for single site
     */
    public function testDuoAdminInitForSingleSite(): void
    {

        $this->helper->method('is_multisite')->willReturn(false);
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $this->helper
            ->expects($this->once())
            ->method('add_settings_section')
            ->withConsecutive(
                ['duo_universal_settings', 'Main Settings', array($settings, 'duo_settings_text'), 'duo_universal_settings']
            );
        $this->helper
            ->expects($this->exactly(6))
            ->method('add_settings_field')
            ->withConsecutive(
                ['duo_client_id', 'Client ID', array($settings, 'duo_settings_client_id'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duo_client_secret', 'Client Secret', array($settings, 'duo_settings_client_secret'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duo_host', 'API hostname', array($settings, 'duo_settings_host'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duo_failmode', 'Failmode', array($settings, 'duo_settings_failmode'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duo_roles', 'Enable for roles:', array($settings, 'duo_settings_roles'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duo_xmlrpc', 'Disable XML-RPC (recommended)', array($settings, 'duo_settings_xmlrpc'), 'duo_universal_settings', 'duo_universal_settings']
            );
        $this->helper
            ->expects($this->exactly(6))
            ->method('register_setting')
            ->withConsecutive(
                ['duo_universal_settings', 'duo_client_id', array($settings, 'duo_client_id_validate')],
                ['duo_universal_settings', 'duo_client_secret', array($settings, 'duo_client_secret_validate')],
                ['duo_universal_settings', 'duo_host'],
                ['duo_universal_settings', 'duo_failmode'],
                ['duo_universal_settings', 'duo_roles', array($settings, 'duo_roles_validate')],
                ['duo_universal_settings', 'duo_xmlrpc', array($settings, 'duo_xmlrpc_validate')]
            );
        $settings->duo_admin_init();
    }

    /**
     * Test duo_update_mu_option update site options with values from $_POST
     */
    public function testDuoMultisidteUpdateWithPostValues(): void
    {
        $this->old_POST = $_POST;
        $duo_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $_POST = array(
            'duo_client_id' => 'mock_id',
            'duo_client_secret' => 'mock_secret',
            'duo_host' => 'mock_host',
            'duo_failmode' => 'mock_failmode',
            'duo_roles' => $duo_roles,
            'duo_xmlrpc' => 'mock_xmlrpc'
        );

        $this->helper
            ->expects($this->exactly(6))
            ->method('update_site_option')
            ->withConsecutive(
                ['duo_client_id', 'mock_id'],
                ['duo_client_secret', 'mock_secret'],
                ['duo_host', 'mock_host'],
                ['duo_failmode', 'mock_failmode'],
                ['duo_roles', $duo_roles],
                ['duo_xmlrpc', 'mock_xmlrpc'],
            );
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $settings->duo_update_mu_options();
        $_POST = $this->old_POST;
    }

    /**
     * Test duo_update_mu_option update site options with empty $_POST
     */
    public function testDuoMultisidteUpdateWithEmptyPostValue(): void
    {
        $this->helper
            ->expects($this->exactly(3))
            ->method('update_site_option')
            ->withConsecutive(
                ['duo_failmode', 'open'],
                ['duo_roles', []],
                ['duo_xmlrpc', 'on'],
            );
        $settings = new Duo\DuoUniversalWordpress\Settings($this->duo_utils);
        $settings->duo_update_mu_options();
    }

}
