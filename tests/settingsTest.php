<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once('duo_settings.php');
require_once('duo_wordpress_helper.php');

final class SettingsTest extends TestCase
{
    /**
     * Test that settings page loads correctly
     * when multisite is enabled
     */
    public function testSettingsPageMultisite(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['is_multisite', 'settings_fields', 'do_settings_sections', 'esc_attr_e'])
           ->getMock();
        $duo_utils = new Duo\DuoUniversalWordpress\Utilities($helper);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $helper->method('is_multisite')->willReturn(true);

        $this->expectOutputRegex('/ms-options/');
        $settings->duo_settings_page();
    }

    /**
     * Test that settings page loads correctly
     * when multisite is disabled
     */
    public function testSettingsPageSingleSite(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['is_multisite', 'settings_fields', 'do_settings_sections', 'esc_attr_e'])
           ->getMock();
        $duo_utils = new Duo\DuoUniversalWordpress\Utilities($helper);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $helper->method('is_multisite')->willReturn(false);

        $this->expectOutputRegex('/action="options/');
        $settings->duo_settings_page();
    }

    /**
     * Test that client id shows up in the output
     */
    public function testSettingsClientID(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['esc_attr'])
           ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
             ->setConstructorArgs(array($helper))
             ->onlyMethods(['duo_get_option'])
             ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-value");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $this->expectOutputRegex('/this-is-a-test-value/');
        $settings->duo_settings_client_id();
    }

    /**
     * Test that an invalid client id yields the empty string
     */
    public function testDuoClientIDValidateInvalid(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['add_settings_error'])
           ->getMock();
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $result = $settings->duo_client_id_validate("invalid id");

        $this->assertEquals($result, "");
    }

    /**
     * Test that a valid client id validates
     */
    public function testDuoClientIDValidateValid(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['add_settings_error'])
           ->getMock();
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $client_id = "DIXXXXXXXXXXXXXXXXXX";

        $result = $settings->duo_client_id_validate($client_id);

        $this->assertEquals($result, $client_id);
    }

    /**
     * Test that client secret shows up in the output
     */
    public function testSettingsClientSecret(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['esc_attr'])
           ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
             ->setConstructorArgs(array($helper))
             ->onlyMethods(['duo_get_option'])
             ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-fake-secret");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $this->expectOutputRegex('/this-is-a-fake-secret/');
        $settings->duo_settings_client_secret();
    }

    /**
     * Test that an invalid client secret yields the empty string
     */
    public function testDuoClientSecretValidateInvalid(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['add_settings_error'])
           ->getMock();
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $result = $settings->duo_client_secret_validate("invalid secret");

        $this->assertEquals($result, "");
    }

    /**
     * Test that a valid client secret validates
     */
    public function testDuoClientSecretValidateValid(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['add_settings_error'])
           ->getMock();
        $duo_utils = $this->createMock(Duo\DuoUniversalWordpress\Utilities::class);
        $duo_utils->wordpress_helper = $helper;
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);
        $client_secret = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";

        $result = $settings->duo_client_secret_validate($client_secret);

        $this->assertEquals($result, $client_secret);
    }

    /**
     * Test that the host shows up in output
     */
    public function testSettingsHostOutput(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['esc_attr'])
           ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
             ->setConstructorArgs(array($helper))
             ->onlyMethods(['duo_get_option'])
             ->getMock();
        $duo_utils->method('duo_get_option')->willReturn("this-is-a-test-host");
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $this->expectOutputRegex('/this-is-a-test-host/');
        $settings->duo_settings_host();
    }

    /**
     * Test that the failmode shows up in output
     * with correct mode selected
     */
    public function testSettingsFailmode(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['esc_attr'])
           ->getMock();
        $helper->method('esc_attr')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
             ->setConstructorArgs(array($helper))
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
        $helper = $this->getMockBuilder(stdClass::class)
           ->addMethods(['before_last_bar'])
           ->getMock();
        $helper->method('before_last_bar')->willReturnArgument(0);
        $duo_utils = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
             ->setConstructorArgs(array($helper))
             ->onlyMethods(['duo_get_option', 'duo_get_roles'])
             ->getMock();

        $duo_utils->method('duo_get_option')->willReturn(["uses_2fa"]);
        $duo_utils->method('duo_get_roles')->willReturn($roles);
        $settings = new Duo\DuoUniversalWordpress\Settings($duo_utils);

        $settings->duo_settings_roles();
        $output = $this->getActualOutput();

        $this->assertEquals(1, preg_match(
            "/name='duo_roles\[uses_2fa\]' type='checkbox' value='1'  checked/",
            $output
        ));
        $this->assertEquals(1, preg_match(
            "/name='duo_roles\[skip_2fa\]' type='checkbox' value=''/",
            $output
        ));
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
}
