<?php
/**
 *
 * @todo add tests for the different template types
 */
namespace tests\phpunit;

require_once dirname(__DIR__) . '/../ErdikoTestCase.php';
require_once dirname(__DIR__) . '/../factories/MyErdikoUser.php';
require_once dirname(__DIR__) . '/../factories/Mock.php';

use erdiko\authenticate\services\JWTAuthentication;
use \tests\ErdikoTestCase;


class JWTAuthenticationTest extends ErdikoTestCase
{

    protected $mockUser;

    protected $mockRole;

    protected $mockUserEntity;

    protected $mockRoleEntity;

    protected $mockRoleData = array(
        "id"    => 1,
        "name"  => "Foo",
    );
    protected $mockUserData = array(
        "email"     =>  "foo@example.com",
        "name"      =>  "Foo Bar",
    );

    protected $params = array(
        'username'      => 'foo@mail.com',
        'password'      => '123',
        'secret_key'    => '123abc',
        'jwt'           => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwiZW1haWwiOiJmb29AZXhhbXBsZS5jb20iLCJuYW1lIjoiRm9vIEJhciIsInJvbGUiOnsiaWQiOjEsIm5hbWUiOiJGb28ifSwiY3JlYXRlZCI6MTQ4NDM2MDc3Mn0.VW92jy5zK2LXdyq7yPGo_ilQK0sYH2yt3hrLYuWSBKM'
    );

    /**
     *
     *
     */
    protected function setUp()
    {
        @session_start();
		$_SESSION = array();
		ini_set("session.use_cookies",0);
        ini_set("session.use_only_cookies",0);

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // mock the user model
        $this->mockUser = $this->getMockBuilder('\erdiko\users\models\User')
                               ->disableOriginalConstructor()
                               ->getMock();

        // mock the role model
        $this->mockRole = $this->getMockBuilder('\erdiko\users\models\Role')
                               ->disableOriginalConstructor()
                               ->getMock();

        // set up mock role
        $this->mockRoleEntity = $this->getMockBuilder('\erdiko\users\entities\Role')
                                     ->disableOriginalConstructor()
                                     ->getMock();

        // iterate over the test data to populate the test entity
        foreach($this->mockRoleData as $key => $val) {
            $this->mockRoleEntity->expects($this->any())
                                 ->method('get' . ucwords($key))
                                 ->will($this->returnValue($val));

        }

        // set up mock user entity
        $this->mockUserEntity = $this->getMockBuilder('\erdiko\users\entities\User')
                                     ->disableOriginalConstructor()
                                     ->getMock();

        foreach($this->mockUserData as $key => $val) {
            $this->mockUserEntity->expects($this->any())
                                 ->method('get' . ucwords($key))
                                 ->will($this->returnValue($val));

        }

        $this->mockUser->expects($this->any())
                       ->method('getUserId')
                       ->will($this->returnValue(1));

        $this->mockUser->expects($this->any())
                       ->method('getEntity')
                       ->will($this->returnValue($this->mockUserEntity));

    }

    /**
     * positive test of the login
     *
     */
    public function testLogin()
    {
        $this->mockUser->expects($this->once())
                       ->method('authenticate')
                       ->will($this->returnValue($this->mockUser));

        $this->mockRole->expects($this->any())
                       ->method('findById')
                       ->will($this->returnValue($this->mockRoleEntity));

        $auth   = new JWTAuthentication($this->mockUser, $this->mockRole);
		$result = $auth->login($this->params);

        $this->assertTrue(!empty($result->user), "user is returned in login response");
        $this->assertTrue(!empty($result->token), "token is returned in login response");
    }

    /**
     * make sure we throw an exception if we lack a secret key
     *
     */
    public function testLoginNoSecret()
    {
        $this->mockUser->expects($this->once())
                       ->method('authenticate')
                       ->will($this->returnValue($this->mockUser));

        $params = $this->params;
        unset($params["secret_key"]);
        $this->expectException('Exception');
        $this->expectExceptionMessage("Secret Key is required to create a JWT");

        $auth   = new JWTAuthentication($this->mockUser, $this->mockRole);
		$result = $auth->login($params);
    }

    /**
     * no user is found
     *
     */
    public function testLoginNoUser()
    {
        $this->mockUser->expects($this->once())
                       ->method('authenticate')
                       ->will($this->returnValue(false));

        $this->expectException('Exception');
        $this->expectExceptionMessage("Invalid username or password");

        $auth   = new JWTAuthentication($this->mockUser, $this->mockRole);
		$result = $auth->login($this->params);
    }

    /**
     * no user role is found
     *
     */
    public function testLoginNoUserRole()
    {

        $this->mockUser->expects($this->once())
                       ->method('authenticate')
                       ->will($this->returnValue($this->mockUser));

        $this->mockRole->expects($this->any())
                       ->method('findById')
                       ->will($this->returnValue(false));

        $this->expectException('Exception');
        $this->expectExceptionMessage("User is missing a role");

        $auth   = new JWTAuthentication($this->mockUser, $this->mockRole);
		$result = $auth->login($this->params);
    }

    /**
     * positive test of the verify method
     *
     */
    public function testVerify()
    {
        $auth = new JWTAuthentication($this->mockUser, $this->mockRole);
        $result = $auth->verify($this->params);

        $this->assertTrue(!empty($result), "results were returned");

        $this->assertTrue((1 === $result->id), "User ID matches as expected");
        $this->assertTrue(($this->mockUserData["email"] === $result->email), "User email matches as expected");
        $this->assertTrue(($this->mockUserData["name"] === $result->name), "User name matches as expected");

        $this->assertTrue(!empty($result->role), "role is not empty");
        $this->assertTrue(($this->mockRoleData["id"] === $result->role->id), "Role ID matches as expected");
        $this->assertTrue(($this->mockRoleData["name"] === $result->role->name), "Role name matches as expected");

    }

    /**
     *
     */
    public function testVerifyNoJWT()
    {
        $params = $this->params;
        unset($params["jwt"]);

        $this->expectException('Exception');
        $this->expectExceptionMessage("JWT is required");

        $auth = new JWTAuthentication($this->mockUser, $this->mockRole);
        $result = $auth->verify($params);
    }

    /**
     *
     */
    public function testVerifyNoSecretKey()
    {
        $params = $this->params;
        unset($params["secret_key"]);

        $this->expectException('Exception');
        $this->expectExceptionMessage("Secret Key is required to decode this JWT");

        $auth = new JWTAuthentication($this->mockUser, $this->mockRole);
        $result = $auth->verify($params);
    }

    /**
     *
     */
    public function testVerifyGarbageJWT()
    {
        $params = $this->params;
        $params["jwt"] = '121222121';

        $this->expectException('Exception');

        $auth = new JWTAuthentication($this->mockUser, $this->mockRole);
        $result = $auth->verify($params);
    }

}
