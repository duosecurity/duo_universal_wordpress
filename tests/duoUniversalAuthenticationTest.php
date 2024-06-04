<?php declare(strict_types=1);

use Duo\DuoUniversal\DuoException;
use Duo\DuoUniversalWordpress;
use PHPUnit\Framework\TestCase;
use WP_Mock\Tools\TestCase as WPTestCase;
require_once 'class-duouniversal-wordpressplugin.php';


final class authenticationTest extends WPTestCase
{
    // Setup "fully functional" mocks for both user_meta and site_options
    function setUpMocks(): void
    {
        $this->user_meta = array();
        $this->site_options = array();

        WP_Mock::userFunction('update_user_meta', [
            'return' => function($user_id, $key, $value) {
                if (!array_key_exists($user_id, $this->user_meta))
                {
                    $this->user_meta[$user_id] = array();
                }
                $this->user_meta[$user_id][$key] = $value;
                return True;
            }
        ]);

        WP_Mock::userFunction('get_user_meta', [
            'return' => function($user_id, $key) {
                if (!array_key_exists($user_id, $this->user_meta) || !array_key_exists($key, $this->user_meta[$user_id]))
                {
                    // This mimics the behavior of the real get_user_meta
                    return '';
                }
                return $this->user_meta[$user_id][$key];
            }
        ]);

        WP_Mock::userFunction('delete_user_meta', [
            'return' => function($user_id, $key) {
                unset($this->user_meta[$user_id][$key]);
                return True;
            }
        ]);

        WP_Mock::userFunction('update_site_option', [
            'return' => function($key, $value) {
                $this->site_options[$key] = $value;
                return True;
            }
        ]);

        WP_Mock::userFunction('get_site_option', [
            'return' => function($key) {
                return $this->site_options[$key];
            }
        ]);

        WP_Mock::userFunction('delete_site_option', [
            'return' => function($key) {
                unset($this->site_options[$key]);
                return True;
            }
        ]);
    }

    function createMockUser() {
        $user = $this->getMockBuilder(stdClass::class)
        ->setMockClassName('WP_User')
        ->getMock();
        $user->user_login = "test user";
        $user->ID = 1;
        return $user;
    }

    function setUp(): void
    {
        $this->duo_client = $this->createMock(Duo\DuoUniversal\Client::class);
        // For filtering and sanitization methods provided by wordpress,
        // simply return the value passed in for filtering unchanged since we
        // don't have the wordpress methods in scope
        WP_Mock::passthruFunction('sanitize_url');
        WP_Mock::passthruFunction('sanitize_text_field');

        $this->duo_utils = $this->createMock(Duo\DuoUniversalWordpress\DuoUniversal_Utilities::class);
    }

    /**
     * Test that update_user_auth_status creates
     * correct user metadata
     */
    function testUpdateUserAuthStatus(): void
    {
        $this->setUpMocks();
        $authentication = new Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin($this->duo_utils, $this->duo_client);
        $authentication->update_user_auth_status(1, "test_status", "redirect", "oidc_state");
        $this->assertEquals($this->user_meta[1]["duo_auth_status"], "test_status");
        $this->assertEquals($this->user_meta[1]["duo_auth_redirect_url"], "redirect");
        $this->assertEquals($this->user_meta[1]["duo_auth_oidc_state"], "oidc_state");
        $this->assertEquals($this->site_options["duo_auth_state_oidc_state"], 1);
    }

    /**
     * Test that clear_current_user_auth prints
     * exceptions raised during metadata deletion
     */
    function testClearAuthException(): void
    {
        // Mocks cannot be overridden once set, so _first_ define our custom override _then_ setup the basic mocks
        WP_Mock::userFunction('delete_user_meta', ['return' => function() { throw (new Exception()); } ]);
        $this->setUpMocks();
        $user = $this->createMockUser();
        WP_Mock::userFunction('wp_get_current_user', [ 'return' => $user ]);
        $this->duo_utils->expects($this->once())->method('duo_debug_log');
        $authentication = new Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin($this->duo_utils, $this->duo_client);

        $authentication->clear_current_user_auth();
    }

    /**
     * Test that clear_current_user_auth removes metadata
     */
    function testClearAuthRemovesMetadata(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        WP_Mock::userFunction('wp_get_current_user', [ 'return' => $user ]);
        WP_Mock::userFunction('delete_user_meta', ['return' => function() { throw (new Exception()); } ]);
        $authentication = new Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin($this->duo_utils, $this->duo_client);
        
        // Make an auth to populate the database
        $authentication->update_user_auth_status($user->ID, "test_status", "redirect", "test_state");

        $this->duo_utils->expects($this->never())->method('duo_debug_log');
        $authentication->clear_current_user_auth();
        $this->assertTrue(empty($this->site_options));
        $this->assertTrue(empty($this->user_meta[$user->ID]));
    }

    /**
     * Test that the redirect URL is set
     */
    function testStartSecondFactorRedirectURL(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->method('get_page_url')->willReturn('fake url');
        WP_Mock::passthruFunction('wp_redirect');

        $authentication->duo_start_second_factor($user);

        $this->assertEquals($this->duo_client->redirect_url, "fake url");
    }

    /**
     * Test that user metadata is intact after starting second factor logged in
     */
    function testDoubleLoginMetadata(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->method('get_page_url')->willReturn('fake url');
        WP_Mock::passthruFunction('wp_redirect');

        $authentication->duo_start_second_factor($user);

        $this->assertEquals($this->duo_client->redirect_url, "fake url");
        $this->assertNotEmpty($this->user_meta);
    }

    /**
     * Test that the user is redirected to the prompt url
     */
    function testPromptRedirect(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $this->duo_client->method('createAuthUrl')->willReturn("prompt url");
        WP_Mock::userFunction('wp_redirect')->with("prompt url")->once();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();

        $authentication->duo_start_second_factor($user);
        $this->assertConditionsMet();
    }

    /**
     * Test that proper state is stored in user metadata
     */
    function testStartSecondFactorMetadata(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->method('get_page_url')->willReturn("test url");
        WP_Mock::passthruFunction('wp_redirect');
        $this->duo_client->method('generateState')->willReturn("test state");

        $authentication->duo_start_second_factor($user);

        $this->assertEquals($this->user_meta[$user->ID]['duo_auth_status'], "in-progress");
        $this->assertEquals($this->user_meta[$user->ID]['duo_auth_redirect_url'], "test url");
        $this->assertEquals($this->user_meta[$user->ID]['duo_auth_oidc_state'], "test state");
        $this->assertEquals($this->site_options['duo_auth_state_test state'], $user->ID);
    }

    /**
     * Test that user is logged out after duo_start_second_factor
     */
    function testStartSecondFactorLogout(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        WP_Mock::passthruFunction('wp_redirect');

        $authentication->duo_start_second_factor($user);
        $this->assertConditionsMet();
    }

    /**
     * Test exit is called after duo_start_second_factor
     */
    function testStartSecondFactorExit(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(['get_page_url', 'exit'])
            ->getMock();
        $authentication->expects($this->once())->method('exit');
        WP_Mock::passthruFunction('wp_redirect');

        $authentication->duo_start_second_factor($user);
    }

    /**
     * Test that a user object is returned out of duo_authenticate_user rather
     * then proceeding with authentication
     */
    function testUserIsNotAString(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_user_from_oidc_state',
                'update_user_auth_status',
                'duo_start_second_factor'
                ]
            )
            ->getMock();
        $authentication->expects($this->never())->method('duo_start_second_factor');
        $user = $this->createMockUser();
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        $result = $authentication->duo_authenticate_user($user);
        $this->assertEquals($result, $user);
    }

    /**
     * Test that duo_authenticate_user returns early if
     * the plugin is not enabled
     */
    function testAuthUserAuthNotEnabled(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(false);
        $user = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_User')
            ->getMock();
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        $user = $this->createMockUser();

        $result = $authentication->duo_authenticate_user($user);
        $this->assertEquals($result, null);
    }

    /**
     * Test that duo_authenticate_user prints error if one is set
     */
    function testAuthUserAPIErrorSet(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log'
                ]
            )
            ->getMock();
        $error = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_Error')
            ->addMethods(["get_error_message"])
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('new_WP_Error')->willReturn($error)->with("Duo authentication failed", "ERROR: Error during login; please contact your system administrator.");
        $authentication->expects($this->once())->method('duo_debug_log')->with($this->equalTo("test error: test description"));
        WP_Mock::passthruFunction('__');
        WP_Mock::passthruFunction('wp_unslash');

        $_GET['duo_code'] = "testcode";
        $_GET['error'] = "test error";
        $_GET['error_description'] = "test description";
        $result = $authentication->duo_authenticate_user();
        $this->assertConditionsMet();
    }

    /**
     * Test that duo_authenticate_user prints error if state is missing
     */
    function testAuthUserStateMissing(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log'
                ]
            )
            ->getMock();
        $error = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_Error')
            ->addMethods(["get_error_message"])
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('new_WP_Error')->willReturn($error)->with("Duo authentication failed", "ERROR: Missing state; Please login again");
        WP_Mock::passthruFunction('__');
        WP_Mock::passthruFunction('wp_unslash');

        $_GET['duo_code'] = "testcode";
        $authentication->duo_authenticate_user();
        $this->assertConditionsMet();
    }

    /**
     * Test that duo_authenticate_user prints error if user unknown
     */
    function testAuthUserUserMissing(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_user_from_oidc_state'
                ]
            )
            ->getMock();
        $error = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_Error')
            ->addMethods(["get_error_message"])
            ->getMock();
        $authentication->method('get_user_from_oidc_state')->willReturn(null);
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('new_WP_Error')->willReturn($error)->with("Duo authentication failed", "ERROR: No saved state please login again");
        WP_Mock::passthruFunction('__');
        WP_Mock::passthruFunction('wp_unslash');
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $authentication->duo_authenticate_user();

        $this->assertConditionsMet();
    }

    /**
     * Test that duo_authenticate_user prints error if token exchange fails
     */
    function testAuthUserExceptionHandling(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_user_from_oidc_state',
                'get_redirect_url'
                ]
            )
            ->getMock();
        $error = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_Error')
            ->addMethods(["get_error_message"])
            ->getMock();
        WP_Mock::passthruFunction('__');
        WP_Mock::passthruFunction('wp_unslash');
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('new_WP_Error')->willReturn($error)->with("Duo authentication failed", "ERROR: Error decoding Duo result. Confirm device clock is correct.");
        $user = $this->createMockUser();
        $authentication->method('get_user_from_oidc_state')->willReturn($user);
        $this->duo_client->method('exchangeAuthorizationCodeFor2FAResult')->willThrowException(new Duo\DuoUniversal\DuoException("there was a problem"));
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $authentication->duo_authenticate_user();

        $this->assertConditionsMet();
    }

    /**
     * Test that duo_authenticate_user updates user status if everything succeeds
     */
    function testAuthUserSuccess(): void
    {
        $this->setUpMocks();
        WP_Mock::passthruFunction('wp_unslash');
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'get_user_from_oidc_state',
                'get_redirect_url'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $user = $this->createMockUser();
        $authentication->method('get_user_from_oidc_state')->willReturn($user);
        $this->duo_utils->method('new_WP_user')->willReturnArgument(1);
        $_GET['duo_code'] = "testcode";
        $_GET['state'] = "teststate";

        $result = $authentication->duo_authenticate_user();

        $this->assertEquals($this->user_meta[$user->ID]["duo_auth_status"], "authenticated");
        $this->assertEquals($result, "test user");
    }

    /**
     * Test that duo_authenticate_user without code starts primary authentication
     * if there is no username
     */
    function testAuthUserNoCodeOrUsername(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
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
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                'error_log',
                ]
            )
            ->getMock();
        WP_Mock::passthruFunction('remove_action');
        WP_Mock::userFunction("wp_authenticate_username_password", [ 'return' => null ]);
        WP_Mock::userFunction("wp_authenticate_email_password", [ 'return' => null ]);
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);

        $result = $authentication->duo_authenticate_user(null, "test user");
        $this->assertEquals($result, null);
    }

    /**
     * Test that duo_authenticate_user works with email
     */
    function testAuthUserPrimaryEmail(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->createMockUser();
        $user->roles = [];
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        WP_Mock::userFunction('wp_authenticate_username_password', [ 'return' => null ]);
        WP_Mock::userFunction('wp_authenticate_email_password', [ 'return' => "EMAIL"])->once();
        WP_Mock::passthruFunction('remove_action');

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($result, "EMAIL");
    }

    /**
     * Test that primary auth with a role that doesn't require 2FA
     * is authenticated
     */
    function testAuthUserPrimaryNo2FARole(): void
    {
        $this->setUpMocks();
        WP_Mock::passthruFunction('remove_action');
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(false);
        $user = $this->createMockUser();
        $user->roles = [];
        WP_Mock::userFunction("wp_authenticate_email_password", [ 'return' => $user ]);
        WP_Mock::userFunction("wp_authenticate_username_password", [ 'return' => $user ]);

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($result, $user);
        $this->assertEquals($this->user_meta[$user->ID]["duo_auth_status"], "authenticated");
    }

    /**
     * Test that primary auth with an error returns an error
     */
    function testAuthUserPrimaryErrorValidatingCredentials(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $user = $this->createMockUser();
        $user->roles = [];
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        WP_Mock::userFunction('wp_authenticate_username_password', [ 'return' => "ERROR" ]);
        WP_Mock::userFunction('wp_authenticate_email_password', [ 'return' => "ERROR"]);
        WP_Mock::passthruFunction('remove_action');

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($result, "ERROR");
    }

    /**
     * Test that primary auth success update auth status to in-progress
     */
    function testAuthUserPrimaryUpdatesAuthStatus(): void
    {
        $this->setUpMocks();
        $user = $this->createMockUser();
        $user->roles = [];
        WP_Mock::userFunction('wp_logout')->once();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
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
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        WP_Mock::userFunction('wp_authenticate_username_password', [ 'return' => $user ]);
        WP_Mock::passthruFunction('remove_action');

        $result = $authentication->duo_authenticate_user(null, "test user");

        $this->assertEquals($this->user_meta[$user->ID]["duo_auth_status"], "in-progress");
    }

    /**
     * Test that exception during second factor is handled based on failmode
     */
    function testAuthUserSecondaryExceptionFailmodeOpen(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
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
        $user = $this->createMockUser();
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        WP_Mock::userFunction('wp_authenticate_username_password', [ 'return' => $user ]);
        WP_Mock::passthruFunction('remove_action');
        WP_Mock::userFunction('wp_logout')->once();
        $this->duo_utils->method('duo_get_option')->willReturn('open');

        $result = $authentication->duo_authenticate_user(null, "test user");
        $this->assertEquals($this->user_meta[$user->ID]["duo_auth_status"], "authenticated");
    }

    /**
     * Test that exception during second factor is handled based on failmode
     */
    function testAuthUserSecondaryExceptionFailmodeClose(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
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
        $user = $this->createMockUser();
        $error = $this->getMockBuilder(stdClass::class)
            ->setMockClassName('WP_Error')
            ->addMethods(["get_error_message"])
            ->getMock();
        $this->duo_utils->method('new_WP_user')->willReturn($user);
        $this->duo_utils->method('new_WP_Error')->willReturn($error)->with("Duo authentication failed", "Error: 2FA Unavailable. Confirm Duo client/secret/host values are correct");
        WP_Mock::passthruFunction('__');
        WP_Mock::passthruFunction('remove_action');
        WP_Mock::userFunction('wp_authenticate_username_password', [ 'return' => $user ]);
        WP_Mock::userFunction('wp_logout')->once();
        $this->duo_utils->method('duo_get_option')->willReturn('closed');

        $result = $authentication->duo_authenticate_user(null, "test user");
        $this->assertFalse(array_key_exists("duo_auth_status", $this->user_meta[$user->ID]));
        $this->assertConditionsMet();
    }

    /**
     * Test that verify auth skips if duo is not enabled
     */
    function testVerifyAuthDisabled(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(false);
        WP_Mock::userFunction('is_multisite', [ 'return' => false ]);
        $authentication->expects($this->once())
            ->method('duo_debug_log')
            ->with($this->equalTo("Duo not enabled, skip auth check."));

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
        $this->assertConditionsMet();
    }

    /**
     * Test that verify auth skips if duo is not enabled for site
     */
    function testVerifyAuthDisabledMultisite(): void
    {
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
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
        WP_Mock::userFunction('is_multisite', [ 'return' => true ]);
        WP_Mock::userFunction('get_current_site', [ 'return' => $site ]);
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
        $this->setUpMocks();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        WP_Mock::userFunction('is_user_logged_in', [ 'return' => false ]);
        $authentication->expects($this->never())->method('duo_debug_log');

        $result = $authentication->duo_verify_auth();

        $this->assertEquals($result, null);
    }

    /**
     * Test that verify auth skips if role does not require MFA
     */
    function testVerifyAuthRoleNotRequire2FA(): void
    {
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_start_second_factor'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        WP_Mock::userFunction('is_user_logged_in', [ 'return' => true ]);
        WP_Mock::userFunction('wp_get_current_user', [ 'return' => $user ]);
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
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_verify_auth_status', 'duo_start_second_factor',
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        WP_Mock::userFunction('is_user_logged_in', [ 'return' => true ]);
        WP_Mock::userFunction('wp_get_current_user', [ 'return' => $user ]);
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
        $user = $this->createMockUser();
        $authentication = $this->getMockBuilder(Duo\DuoUniversalWordpress\DuoUniversal_WordPressPlugin::class)
            ->setConstructorArgs(array($this->duo_utils, $this->duo_client))
            ->onlyMethods(
                [
                'duo_debug_log', 'duo_verify_auth_status', 'duo_start_second_factor', 'exit'
                ]
            )
            ->getMock();
        $this->duo_utils->method('duo_auth_enabled')->willReturn(true);
        WP_Mock::userFunction('is_user_logged_in', [ 'return' => true ]);
        WP_Mock::userFunction('wp_get_current_user', [ 'return' => $user ]);
        WP_Mock::userFunction('wp_logout')->once();
        WP_Mock::userFunction('wp_redirect')->once();
        WP_Mock::userFunction('wp_login_url')->once();
        $this->duo_utils->method('duo_role_require_mfa')->willReturn(true);
        $authentication->method('duo_verify_auth_status')->willReturn(false);
        $authentication->expects($this->once())->method('exit');

        $result = $authentication->duo_verify_auth();

        $this->assertConditionsMet();
    }
}
