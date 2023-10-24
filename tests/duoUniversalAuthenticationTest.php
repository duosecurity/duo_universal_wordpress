<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
require_once 'duo_universal_authentication.php';
require_once 'duo_universal_wordpress_helper.php';

final class authenticationTest extends TestCase
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
     * Test that update_user_auth_status creates
     * correct transients
     */
    function testUpdateUserAuthStatus(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $authentication = new DuoUniversal_WordpressPlugin($this->duo_utils, $this->duo_client);
        $authentication->update_user_auth_status("user", "test_status", "redirect", "oidc_state");
        $this->assertEquals($map["duo_auth_user_status"], "test_status");
        $this->assertEquals($map["duo_auth_user_redirect_url"], "redirect");
        $this->assertEquals($map["duo_auth_user_oidc_state"], "oidc_state");
        $this->assertEquals($map["duo_auth_state_oidc_state"], "user");
    }

    /**
     * Test that clear_current_user_auth prints
     * exceptions raised during transient deletion
     */
    function testClearAuthException(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $this->helper->method('delete_transient')->willThrowException(new Exception());
        $this->helper->method('get_transient')->willReturn('test user');
        $this->helper->method('wp_get_current_user')->willReturn($user);
        $this->duo_utils->expects($this->once())->method('duo_debug_log');
        $authentication = new DuoUniversal_WordpressPlugin($this->duo_utils, $this->duo_client);

        $authentication->clear_current_user_auth();
    }

    /**
     * Test that clear_current_user_auth removes transients
     */
    function testClearAuthRemovesTransients(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $map = array(
            "duo_auth_".$user->user_login."_status" => "status",
            "duo_auth_".$user->user_login."_redirect_url" => "example.com",
            "duo_auth_".$user->user_login."_oidc_state" => "state",
            "duo_auth_state_state" => $user->user_login
        );
        $delete_callback = function ($key) use (&$map) {
            unset($map[$key]);
        };
        $this->helper->method('delete_transient')->willReturnCallback($delete_callback);
        $this->helper->method('get_transient')->willReturn('state');
        $this->helper->method('wp_get_current_user')->willReturn($user);
        $this->duo_utils->expects($this->never())->method('duo_debug_log');
        $authentication = new DuoUniversal_WordpressPlugin($this->duo_utils, $this->duo_client);

        $authentication->clear_current_user_auth();

        $this->assertTrue(empty($map));
    }

    /**
     * Test that the redirect URL is set
     */
    function testStartSecondFactorRedirectURL(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->method('get_page_url')->willReturn('fake url');

        $authentication->duo_start_second_factor($user);

        $this->assertEquals($this->duo_client->redirect_url, "fake url");
    }

    /**
     * Test that the user is redirected to the prompt url
     */
    function testPromptRedirect(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $this->duo_client->method('createAuthUrl')->willReturn("prompt url");
        $this->helper->expects($this->once())
            ->method('wp_redirect')
            ->with($this->equalTo("prompt url"));
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();

        $authentication->duo_start_second_factor($user);
    }

    /**
     * Test that proper state is stored in transients
     */
    function testStartSecondFactorTransients(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->method('get_page_url')->willReturn("test url");
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $this->duo_client->method('generateState')->willReturn("test state");

        $authentication->duo_start_second_factor($user);

        $this->assertEquals($map['duo_auth_test user_status'], "in-progress");
        $this->assertEquals($map['duo_auth_test user_redirect_url'], "test url");
        $this->assertEquals($map['duo_auth_test user_oidc_state'], "test state");
        $this->assertEquals($map['duo_auth_state_test state'], "test user");
    }

    /**
     * Test that user is logged out after duo_start_second_factor
     */
    function testStartSecondFactorLogout(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $this->helper->expects($this->once())->method('wp_logout');

        $authentication->duo_start_second_factor($user);
    }

    /**
     * Test exit is called after duo_start_second_factor
     */
    function testStartSecondFactorExit(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->expects($this->once())->method('exit');

        $authentication->duo_start_second_factor($user);
    }

    /**
     * Test that a user object is returned out of duo_authenticate_user rather
     * then proceeding with authentication
     */
    function testUserIsNotAString(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_username_from_oidc_state',
                'update_user_auth_status',
                'duo_start_second_factor'
                ]
            )
            ->getMock();
        $authentication->expects($this->never())->method('duo_start_second_factor');
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";

        $result = $authentication->duo_authenticate_user($user);
        $this->assertEquals($result, $user);
    }

    /**
     * Test that duo_authenticate_user returns early if
     * the plugin is not enabled
     */
    function testAuthUserAuthNotEnabled(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(false);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";

        $result = $authentication->duo_authenticate_user($user);
        $this->assertEquals($result, null);
    }

    /**
     * Test that duo_authenticate_user prints error if one is set
     */
    function testAuthUserAPIErrorSet(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method('translate')->willReturnArgument(0);
        $this->helper->method('WP_Error')->willReturnArgument(1);

        $_GET['duo_code'] = "testcode";
        $_GET['error'] = "test error";
        $_GET['error_description'] = "test description";
        $result = $authentication->duo_authenticate_user();
        $this->assertRegExp("/test description/", $result);
    }

    /**
     * Test that duo_authenticate_user prints error if state is missing
     */
    function testAuthUserStateMissing(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method('translate')->willReturnArgument(0);
        $this->helper->method('WP_Error')->willReturnArgument(1);

        $_GET['duo_code'] = "testcode";
        $result = $authentication->duo_authenticate_user();
        $this->assertRegExp("/Missing state/", $result);
    }

    /**
     * Test that duo_authenticate_user prints error if user unknown
     */
    function testAuthUserUserMissing(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_username_from_oidc_state'
                ]
            )
            ->getMock();
        $authentication->method('get_username_from_oidc_state')->willReturn(null);
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method('translate')->willReturnArgument(0);
        $this->helper->method('WP_Error')->willReturnArgument(1);
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $result = $authentication->duo_authenticate_user();

        $this->assertRegExp("/No saved state/", $result);
    }

    /**
     * Test that duo_authenticate_user prints error if token exchange fails
     */
    function testAuthUserExceptionHandling(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_username_from_oidc_state',
                'get_redirect_url'
                ]
            )
            ->getMock();
        $this->helper->method('translate')->willReturnArgument(0);
        $this->helper->method('WP_Error')->willReturnArgument(1);
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $authentication->method('get_username_from_oidc_state')->willReturn("test user");
        $this->duo_client->method('exchangeAuthorizationCodeFor2FAResult')->willThrowException(new Duo\DuoUniversal\DuoException("there was a problem"));
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $result = $authentication->duo_authenticate_user();

        $this->assertRegExp("/Error decoding Duo result/", $result);
    }

    /**
     * Test that duo_authenticate_user updates user status if everything succeeds
     */
    function testAuthUserSuccess(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_username_from_oidc_state',
                'get_redirect_url'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $authentication->method('get_username_from_oidc_state')->willReturn("test user");
        $this->helper->method("WP_User")->willReturnArgument(1);
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $result = $authentication->duo_authenticate_user();

        $this->assertEquals($map["duo_auth_test user_status"], "authenticated");
        $this->assertEquals($result, "test user");
    }

    /**
     * Test that duo_authenticate_user without code starts primary authentication
     * if there is no username
     */
    function testAuthUserNoCodeOrUsername(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $authentication->expects($this->once())->method('duo_debug_log')->with($this->equalTo("Starting primary authentication"));

        $result = $authentication->duo_authenticate_user();

        $this->assertEquals($result, null);
    }

    /**
     * Test that duo_authenticate_user without code but with username
     * returns null if wordpress user cannot be found
     */
    function testAuthUserPrimaryNoUser(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'error_log',
                ]
            )
            ->getMock();
        $authentication->expects($this->once())->method('error_log')->with($this->equalTo("Failed to retrieve WP user test user"));
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method('WP_User')->willReturn(null);

        $result = $authentication->duo_authenticate_user(null, "test user");
    }

    /**
     * Test that primary auth with a role that doesn't require 2FA
     * is authenticated
     */
    function testAuthUserPrimaryNo2FARole(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(false);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";
        $user->roles = [];
        $this->helper->method('WP_User')->willReturn($user);

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($result, null);
        $this->assertEquals($map["duo_auth_test user_status"], "authenticated");
    }

    /**
     * Test that primary auth with an error returns an error
     */
    function testAuthUserPrimaryErrorValidatingCredentials(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";
        $user->roles = [];
        $this->helper->method('WP_User')->willReturn($user);
        $this->helper->method('wp_authenticate_username_password')->willReturn("ERROR");

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($result, "ERROR");
    }

    /**
     * Test that primary auth success update auth status to in-progress
     */
    function testAuthUserPrimaryUpdatesAuthStatus(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $authentication->expects($this->once())->method('duo_start_second_factor');
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";
        $user->roles = [];
        $this->helper->method('WP_User')->willReturn($user);
        $this->helper->method('wp_authenticate_username_password')->willReturn($user);

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($map["duo_auth_test user_status"], "in-progress");
    }

    /**
     * Test that exception during second factor is handled based on failmode
     */
    function testAuthUserSecondaryExceptionFailmodeOpen(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $authentication->method('duo_start_second_factor')->willThrowException(new Duo\DuoUniversal\DuoException("error during auth"));
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";
        $user->roles = [];
        $this->helper->method('WP_User')->willReturn($user);
        $this->helper->method('wp_authenticate_username_password')->willReturn($user);
        $this->duo_utils->method('duo_get_option')->willReturn('open');

        $result = $authentication->duo_authenticate_user(null, "test user");
        $this->assertEquals($map["duo_auth_test user_status"], "authenticated");
    }

    /**
     * Test that exception during second factor is handled based on failmode
     */
    function testAuthUserSecondaryExceptionFailmodeClose(): void
    {
        $map = array();
        $callback = function ($key, $value, $expiration) use (&$map) {
            $map[$key] = $value;
        };
        $delete_callback = function ($key) use (&$map) {
            unset($map[$key]);
        };
        $this->helper->method('set_transient')->willReturnCallback($callback);
        $this->helper->method('delete_transient')->willReturnCallback($delete_callback);
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $authentication->method('duo_start_second_factor')->willThrowException(new Duo\DuoUniversal\DuoException("error during auth"));
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $user->user_login = "test user";
        $user->roles = [];
        $this->helper->method('WP_User')->willReturn($user);
        $this->helper->method('WP_Error')->willReturnArgument(1);
        $this->helper->method('translate')->willReturnArgument(0);
        $this->helper->method('wp_authenticate_username_password')->willReturn($user);
        $this->duo_utils->method('duo_get_option')->willReturn('closed');

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertFalse(array_key_exists("duo_auth_test user_status", $map));
        $this->assertRegExp("/2FA Unavailable/", $result);
    }

    /**
     * Test that verify auth skips if duo is not enabled
     */
    function testVerifyAuthDisabled(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(false);
        $authentication->expects($this->once())
            ->method('duo_debug_log')
            ->with($this->equalTo("Duo not enabled, skip auth check."));

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth skips if duo is not enabled for site
     */
    function testVerifyAuthDisabledMultisite(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $site = $this->createMock(stdClass::class);
        $site->site_name = "test site";
        $this->duo_utils->method('duo_auth_enabled')->willReturn(false);
        $this->helper->method('is_multisite')->willReturn(true);
        $this->helper->method('get_current_site')->willReturn($site);
        $authentication->expects($this->once())
            ->method('duo_debug_log')
            ->with($this->equalTo("Duo not enabled on test site"));

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth skips if user not logged in
     */
    function testVerifyAuthNotLoggedIn(): void
    {
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method("is_user_logged_in")->willReturn(false);
        $authentication->expects($this->never())->method('duo_debug_log');

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth skips if role does not require MFA
     */
    function testVerifyAuthRoleNotRequire2FA(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_start_second_factor'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method("is_user_logged_in")->willReturn(true);
        $this->helper->method('wp_get_current_user')->willReturn($user);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(false);
        $authentication->expects($this->at(1))
            ->method('duo_debug_log')
            ->with($this->equalTo("User test user allowed"));
        $authentication->expects($this->never())->method('duo_start_second_factor');

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth skips if user is already verified
     */
    function testVerifyAuthAlreadyVerified(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_verify_auth_status', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method("is_user_logged_in")->willReturn(true);
        $this->helper->method('wp_get_current_user')->willReturn($user);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $authentication->method('duo_verify_auth_status')->willReturn(true);
        $authentication->expects($this->at(2))
            ->method('duo_debug_log')
            ->with($this->equalTo("User test user allowed"));
        $authentication->expects($this->never())->method('duo_start_second_factor');

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth starts second factor if needed
     */
    function testVerifyAuthNeeds2FA(): void
    {
        $user = $this->createMock(stdClass::class);
        $user->user_login = "test user";
        $authentication = $this->getMockBuilder(DuoUniversal_WordpressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_verify_auth_status', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->helper->method("is_user_logged_in")->willReturn(true);
        $this->helper->method('wp_get_current_user')->willReturn($user);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $authentication->method('duo_verify_auth_status')->willReturn(false);
        $authentication->expects($this->once())->method('duo_start_second_factor');

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }
}
