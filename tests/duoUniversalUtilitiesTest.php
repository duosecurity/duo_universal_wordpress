<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once plugin_dir_path( __FILE__ ) . 'class-duouniversal-utilities.php';

final class UtilitiesTest extends TestCase
{
    /**
     * Test that test_duo_auth_enabled returns false
     * when XMLRPC_REQUEST is defined
     */
    public function testDuoAuthXMLRPCEnabled(): void
    {
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
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
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
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
        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
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

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->onlyMethods(['duo_get_option', 'duo_get_roles'])
            ->getMock();
        $command->method('duo_get_option')->willReturn([]);
        $command->method('duo_get_roles')->willReturn($roles);

        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->roles = [];
        $user->user_login = "test";
        
        $this->assertTrue($command->duo_role_require_mfa($user));
    }

    /**
     * Test that test duo_role_require_mfa returns false
     * if the role is disabled
     */
    public function testDuoRoleRequireRoleDisabled(): void
    {
        $duoup_roles = array(
            "other" => true
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->onlyMethods(['duo_get_roles', 'duo_get_option'])
            ->getMock();
        $command->method('duo_get_option')->willReturn($duoup_roles);
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
        $duoup_roles = array(
            "test" => true
        );
        $roles = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_names'])
            ->getMock();
        $roles->method('get_names')->willReturn($duoup_roles);

        $command = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class)
            ->onlyMethods(['duo_get_roles', 'duo_get_option'])
            ->getMock();
        $command->method('duo_get_option')->willReturn($duoup_roles);
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
        WP_Mock::userFunction('is_multisite', [ 'return' => false ]);
        WP_Mock::userFunction('get_option', [ 'return' => "value" ])->once();

        $duo_utils = new Duo\DuoUniversalWordpress\DuoUniversal_Utilities();
        $this->assertEquals($duo_utils->duo_get_option("test"), 'value');
    }

    /**
     * Test that test get_option returns site options when
     * multisite is enabled
     */
    public function testDuoGetOptionMultiSite(): void
    {
        WP_Mock::userFunction('is_multisite', [ 'return' => true ]);
        WP_Mock::userFunction('get_site_option', [ 'return' => "value" ])->once();

        $duo_utils = new Duo\DuoUniversalWordpress\DuoUniversal_Utilities();
        $this->assertEquals($duo_utils->duo_get_option("test"), 'value');
    }
}
