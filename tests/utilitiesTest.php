<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once 'utilities.php';
require_once 'duo_wordpress_helper.php';

final class UtilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        $helper = new Duo\DuoUniversalWordpress\WordpressHelper();
        $this->wordpress_helper = $helper;
    }

    /**
     * Test that test_duo_auth_enabled returns false
     * when XMLRPC_REQUEST is defined
     */
    public function testDuoAuthXMLRPCEnabled(): void
    {
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['xmlrpc_enabled'])
            ->getMock();
        $command->method('xmlrpc_enabled')->willReturn(true);

        $result = $command->duo_auth_enabled();
        $this->assertFalse($result, "return false if XMLRPC_REQUEST is set");
    }

    /**
     * Test that test_duo_auth_enabled returns false
     * when options are missing
     */
    public function testDuoAuthMissingOptions(): void
    {
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['duo_debug_log', 'duo_get_option'])
            ->getMock();
        $command->method('duo_get_option')->willReturn('');

        $result = $command->duo_auth_enabled();
        $this->assertFalse($result, "return false if options are empty");
    }

    /**
     * Test that test_duo_auth_enabled returns true
     * during the happy path
     */
    public function testDuoAuthHappyPath(): void
    {
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['duo_debug_log', 'duo_get_option', 'xmlrpc_enabled'])
            ->getMock();
        $command->method('duo_get_option')->willReturn(true);
        $command->method('xmlrpc_enabled')->willReturn(false);

        $result = $command->duo_auth_enabled();
        $this->assertTrue($result, "return true if everything is good");
    }

    /**
     * Test that test duo_role_require_mfa returns true
     * if the user has empty roles
     */
    public function testDuoRoleRequireMFAEmpty(): void
    {
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn(
            array(
            "test" => true
            )
        );

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['duo_get_option', 'duo_get_roles'])
            ->getMock();
        $command->method('duo_get_option')->willReturn([]);
        $command->method('duo_get_roles')->willReturn($roles);

        $user = new stdClass();
        $user->roles = [];
        
        $this->assertTrue($command->duo_role_require_mfa($user));
    }

    /**
     * Test that test duo_role_require_mfa returns false
     * if the role is disabled
     */
    public function testDuoRoleRequireRoleDisabled(): void
    {
        $duo_roles = array(
            "other" => true
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['duo_get_roles', 'duo_get_option'])
            ->getMock();
        $command->method('duo_get_option')->willReturn($duo_roles);
        $command->method('duo_get_roles')->willReturn($roles);

        $user = new stdClass();
        $user->roles = ["test"];
        
        $this->assertFalse($command->duo_role_require_mfa($user));
    }

    /**
     * Test that test duo_role_require_mfa returns true
     * if the role is enabled
     */
    public function testDuoRoleRequireRoleEnabled(): void
    {
        $duo_roles = array(
            "test" => true
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duo_roles);

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\Utilities::class)
            ->setConstructorArgs(array($this->wordpress_helper))
            ->onlyMethods(['duo_get_roles', 'duo_get_option'])
            ->getMock();
        $command->method('duo_get_option')->willReturn($duo_roles);
        $command->method('duo_get_roles')->willReturn($roles);

        $user = new stdClass();
        $user->roles = ["test"];
        
        $this->assertTrue($command->duo_role_require_mfa($user));
    }

    /**
     * Test that test get_option returns non-site options when
     * multisite is disabled
     */
    public function testDuoGetOptionSingleSite(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['is_multisite', 'get_option'])
            ->getMock();
        $helper->method('is_multisite')->willReturn(false);
        $helper->expects($this->once())->method('get_option')->willReturn("value");

        $duo_utils = new Duo\DuoUniversalWordpress\Utilities($helper);
        $this->assertEquals($duo_utils->duo_get_option("test"), 'value');
    }

    /**
     * Test that test get_option returns site options when
     * multisite is enabled
     */
    public function testDuoGetOptionMultiSite(): void
    {
        $helper = $this->getMockBuilder(stdClass::class)
            ->addMethods(['is_multisite', 'get_site_option'])
            ->getMock();
        $helper->method('is_multisite')->willReturn(true);
        $helper->expects($this->once())->method('get_site_option')->willReturn("value");

        $duo_utils = new Duo\DuoUniversalWordpress\Utilities($helper);
        $this->assertEquals($duo_utils->duo_get_option("test"), 'value');
    }
}
