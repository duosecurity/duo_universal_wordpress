<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once 'class-duouniversal-settings.php';
require_once 'class-duouniversal-wordpresshelper.php';

final class SettingsTest extends TestCase
{

    function setUp(): void
    {
        $this->duo_client = $this->createMock(Duo\DuoUniversal\Client::class);
        $this->helper = $this->createMock(Duo\DuoUniversalWordpress\DuoUniversal_WordpressHelper::class);
        // For filtering and sanitization methods provided by wordpress,
        // simply return the value passed in for filtering unchanged since we
        // don't have the wordpress methods in scope
        $this->helper->method('apply_filters')->willReturnArgument(1);
        $this->helper->method('sanitize_url')->willReturnArgument(0);
        $this->helper->method('sanitize_text_field')->willReturnArgument(0);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $this->duo_utils = $this->createMock(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class);
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
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

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
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $settings->duo_settings_page();
        $this->expectOutputRegex('/action="options/');
    }

    /**
     * Test that client id shows up in the output
     */
    public function testSettingsClientID(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-value");
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $settings->duo_settings_client_id();
        $this->expectOutputRegex('/this-is-a-test-value/');
    }

    /**
     * Test that an invalid client id yields the empty string
     */
    public function testDuoClientIDValidateInvalid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $result = $settings->duoup_client_id_validate("invalid id");

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
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $result = $settings->duoup_client_id_validate("invalid id");

        $this->assertEquals($id, $result);
    }

    /**
     * Test that a valid client id validates
     */
    public function testDuoClientIDValidateValid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $client_id = "DIXXXXXXXXXXXXXXXXXX";

        $result = $settings->duoup_client_id_validate($client_id);

        $this->assertEquals($result, $client_id);
    }

    /**
     * Test that client secret doesn't show up in the output if set
     */
    public function testSettingsClientSecret(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-fake-secret");
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $settings->duo_settings_client_secret();
        $this->expectOutputRegex("/".Duo\DuoUniversalWordpress\SECRET_PLACEHOLDER."/");
    }

    /**
     * Test that an invalid client secret yields the empty string
     */
    public function testDuoClientSecretValidateInvalid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class);
        $duo_utils->wordpress_helper = $this->helper;
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $result = $settings->duoup_client_secret_validate("invalid secret");

        $this->assertEquals($result, "");
    }

    /**
     * Test that the dummy secret doesn't overwrite settings
     */
    public function testDuoClientSecretValidateDummyDoesntSave(): void
    {
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $this->duo_utils->method('duo_get_option')->willReturn("current secret that is 40 character long");

        $result = $settings->duoup_client_secret_validate(Duo\DuoUniversalWordpress\SECRET_PLACEHOLDER);

        $this->assertEquals($result, "current secret that is 40 character long");
    }

    /**
     * Test that a valid client secret validates
     */
    public function testDuoClientSecretValidateValid(): void
    {
        $this->helper->method('add_settings_error')->willReturn(null);
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class);
        $duo_utils->wordpress_helper = $this->helper;
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);
        $client_secret = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";

        $result = $settings->duoup_client_secret_validate($client_secret);

        $this->assertEquals($result, $client_secret);
    }

    /**
     * Test that an invalid client secret doesn't clear current secret
     */
    public function testDuoClientSecretValidateInvalidNoClear(): void
    {
        $original_secret = "current secret that is 40 character long";
        $this->duo_utils->method('duo_get_option')->willReturn($original_secret);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $client_secret = "bad secret";

        $result = $settings->duoup_client_secret_validate($client_secret);

        $this->assertEquals($result, $original_secret);
    }

    /**
     * Test that a valid api host validates
     */
    public function testDuoHostValidateValid(): void
    {
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $host = 'api-duo1.duo.test';
        $result = $settings->duoup_api_host_validate($host);
        $this->assertEquals($result, $host);
    }

    /**
     * Test that an invalid host is rejected and the original host value is
     * returned/preserved
     */
    public function testDuoHostInvalid(): void
    {
        $original_host = 'api-duo1.duo.test';
        $this->duo_utils->method('duo_get_option')->willReturn($original_host);
        $this->helper->method('add_settings_error')->willReturn(null);

        $this->helper->expects($this->once())
            ->method('add_settings_error')
            ->with('duoup_api_host', '', 'Host is not valid');

        // All duo API hostnames start with 'api-'
        $invalid_host = 'api.duo.test';
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $result = $settings->duoup_api_host_validate($invalid_host);

        // The original valid host value should be returned/preserved
        $this->assertEquals($result, $original_host);
    }

    /**
     * Test that in the case a host value has multiple 'api-' prefixes
     * it is reported as a invalid host.
     */
    public function testDuoHostDoubleApiPrefix(): void
    {
        $original_host = 'api-duo1.duo.test';
        $this->duo_utils->method('duo_get_option')->willReturn($original_host);
        $this->helper->method('add_settings_error')->willReturn(null);

        $this->helper->expects($this->once())
            ->method('add_settings_error')
            ->with('duoup_api_host', '', 'Host is not valid');

        $invalid_host = 'api-api-duo1.duo.test';
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $result = $settings->duoup_api_host_validate($invalid_host);

        // The original valid host value should be returned/preserved
        $this->assertEquals($result, $original_host);
    }

    /**
     * Test that the host shows up in output
     */
    public function testSettingsHostOutput(): void
    {
        $this->helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-host");
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

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
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("closed");
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $this->expectOutputRegex('/"closed" selected/');
        $settings->duo_settings_failmode();
    }

    /**
     * Test that selected roles show up as checked
     * when generating role HTML
     */
    public function testSettingsRoles(): void
    {
        $duoup_roles = array(
            "uses_2fa" => true,
            "skip_2fa" => false
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);
        $this->helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($this->helper))
            ->onlyMethods(['duo_get_option', 'duo_get_roles'])
            ->getMock();

        $duo_utils->method('duo_get_option')->willReturn(["uses_2fa"]);
        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $settings->duo_settings_roles();
        $output = $this->getActualOutput();

        $this->assertEquals(
            1, preg_match(
                "/name='duoup_roles\[uses_2fa\]' type='checkbox' value='1' checked/",
                $output
            )
        );
        $this->assertEquals(
            1, preg_match(
                "/name='duoup_roles\[skip_2fa\]' type='checkbox' value=''/",
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
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['esc_attr'])
            ->getMock();
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($helper))
            ->onlyMethods(['duo_get_option'])
            ->getMock();

        $duo_utils->method('duo_get_option')->willReturn('off');
        $helper->method('esc_attr')->willReturnArgument(0);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $result = $settings->duo_settings_xmlrpc();
        $output = $this->getActualOutput();

        $this->expectOutputRegex('/checked/');
    }

    public function testDuoFailmodeValid(): void
    {
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $failmode = 'closed';
        $result = $settings->duoup_failmode_validate($failmode);
        $this->assertEquals($result, $failmode);
    }

    public function testDuoFailmodeInvalid(): void
    {
        $original_failmode = 'closed';
        $this->duo_utils->method('duo_get_option')->willReturn($original_failmode);
        $this->helper->method('add_settings_error')->willReturn(null);

        $this->helper->expects($this->once())
            ->method('add_settings_error')
            ->with('duoup_failmode', '', 'Failmode value is not valid');

        // All duo API hostnames start with 'api-'
        $invalid_failmode = 'foobar';
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $result = $settings->duoup_failmode_validate($invalid_failmode);

        // The original valid host value should be returned/preserved
        $this->assertEquals($result, $original_failmode);
    }

    /**
     * Test that duo_role_validate returns the list
     * of options if all are valid
     */
    public function testDuoRolesValidateGood(): void
    {
        $duoup_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);

        $this->helper->method('before_last_bar')->willReturnArgument(0);
        $this->duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $input = array(
            "Editor" => "role"
        );

        $result = $settings->duoup_roles_validate($input);

        $this->assertEquals($result, $input);
    }

    /**
     * Test that duo_role_validate returns the empty
     * array if falsey options are passed
     */
    public function testDuoRolesValidateEmpty(): void
    {
        $this->duo_utils->wordpress_helper = null;
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $this->assertEmpty($settings->duoup_roles_validate(1));
        $this->assertEmpty($settings->duoup_roles_validate(array()));
        $this->assertEmpty($settings->duoup_roles_validate(false));
    }

    /**
     * Test that duo_role_validate removes bad options
     * from selected role array
     */
    public function testDuoRolesValidateBadOptionsAreRemoved(): void
    {
        $duoup_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['before_last_bar'])
            ->getMock();
        $helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->setConstructorArgs(array($helper))
            ->onlyMethods(['duo_get_roles'])
            ->getMock();

        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($duo_utils);

        $result = $settings->duoup_roles_validate(
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
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $settings->duo_add_page();
    }

    /**
     * Test that duo_add_page being called in multisite
     */
    public function testDuoAddPageMultisite(): void
    {
        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->expects($this->never())->method('add_options_page');
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $settings->duo_add_page();
    }

    /**
     * Test that duo_add_site_option not add already exist options
     */
    public function testDuoAddDuplicateSiteOption(): void
    {
        $this->duo_utils->method('duo_get_option')->willReturn(true);
        $this->helper->expects($this->never())->method('add_site_option');
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

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
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);

        $settings->duo_add_site_option("FakeOption");
    }

    /**
     * Test that duo_admin_init add correct options for multisite
     */
    public function testDuoAdminInitForMultisite(): void
    {
        $duoup_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);
        $this->duo_utils->method('duo_get_roles')->willReturn($roles);

        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->method('before_last_bar')->will($this->returnArgument(0));

        $settings = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Settings::class)
            ->setConstructorArgs(array($this->duo_utils))
            ->onlyMethods(['duo_add_site_option'])
            ->getMock();

        $settings->expects($this->exactly(6))
            ->method('duo_add_site_option')
            ->withConsecutive(
                ['duoup_client_id', ''],
                ['duoup_client_secret', ''],
                ['duoup_api_host', ''],
                ['duoup_failmode', ''],
                ['duoup_roles', $duoup_roles],
                ['duoup_xmlrpc', 'off'],
            );

        $settings->duo_admin_init();
    }

    /**
     * Test that duo_admin_init add correct options for single site
     */
    public function testDuoAdminInitForSingleSite(): void
    {

        $this->helper->method('is_multisite')->willReturn(false);
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
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
                ['duoup_client_id', 'Client ID', array($settings, 'duo_settings_client_id'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duoup_client_secret', 'Client Secret', array($settings, 'duo_settings_client_secret'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duoup_api_host', 'API hostname', array($settings, 'duo_settings_host'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duoup_failmode', 'Failmode', array($settings, 'duo_settings_failmode'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duoup_roles', 'Enable for roles:', array($settings, 'duo_settings_roles'), 'duo_universal_settings', 'duo_universal_settings'],
                ['duoup_xmlrpc', 'Disable XML-RPC (recommended)', array($settings, 'duo_settings_xmlrpc'), 'duo_universal_settings', 'duo_universal_settings']
            );
        $this->helper
            ->expects($this->exactly(6))
            ->method('register_setting')
            ->withConsecutive(
                ['duo_universal_settings', 'duoup_client_id', array($settings, 'duoup_client_id_validate')],
                ['duo_universal_settings', 'duoup_client_secret', array($settings, 'duoup_client_secret_validate')],
                ['duo_universal_settings', 'duoup_api_host'],
                ['duo_universal_settings', 'duoup_failmode'],
                ['duo_universal_settings', 'duoup_roles', array($settings, 'duoup_roles_validate')],
                ['duo_universal_settings', 'duoup_xmlrpc', array($settings, 'duoup_xmlrpc_validate')]
            );
        $settings->duo_admin_init();
    }

    /**
     * Test duo_update_mu_option update site options with values from $_POST
     */
    public function testDuoMultisiteUpdateWithPostValues(): void
    {
        $this->old_POST = $_POST;
        $duoup_roles = array(
            "Editor" => "editor",
            "Author" => "author",
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);
        $this->duo_utils->method('duo_get_roles')->willReturn($roles);

        $_POST = array(
            'duoup_client_id' => 'DIAAAAAAAAAAAAAAAAAA',
            'duoup_client_secret' => str_repeat('aBc123As3cr3t4uandme', 2),
            'duoup_api_host' => 'api-duo1.duo.test',
            'duoup_failmode' => 'closed',
            'duoup_roles' => $duoup_roles,
            'duoup_xmlrpc' => 'off'
        );

        $this->helper
            ->expects($this->exactly(6))
            ->method('update_site_option')
            ->withConsecutive(
                ['duoup_client_id', 'DIAAAAAAAAAAAAAAAAAA'],
                ['duoup_client_secret', str_repeat('aBc123As3cr3t4uandme', 2)],
                ['duoup_api_host', 'api-duo1.duo.test'],
                ['duoup_failmode', 'closed'],
                ['duoup_roles', $duoup_roles],
                ['duoup_xmlrpc', 'off'],
            );
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $settings->duo_update_mu_options();
        $_POST = $this->old_POST;
    }

    /**
     * Test duo_update_mu_option update site options with empty $_POST
     */
    public function testDuoMultisiteUpdateWithEmptyPostValue(): void
    {
        $this->helper
            ->expects($this->exactly(3))
            ->method('update_site_option')
            ->withConsecutive(
                ['duoup_failmode', 'open'],
                ['duoup_roles', []],
                ['duoup_xmlrpc', 'on'],
            );
        $settings = new Duo\DuoUniversalWordpress\DuoUniversal_Settings($this->duo_utils);
        $settings->duo_update_mu_options();
    }

}
