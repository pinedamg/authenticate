<?php
/**
 * JWTAuthenticator
 *
 * Authenticator class that creates and validates JWT via the JWTAuth Service
 *
 * @note this class should extend Basic
 *
 * @note this should use the JWT class for encode/decode
 *
 * @package     erdiko/authenticate/services
 * @copyright   Copyright (c) 2017, Arroyo Labs, http://www.arroyolabs.com
 * @author      Andy Armstrong, andy@arroyolabs.com
 */

namespace erdiko\authenticate\services;

use erdiko\authenticate\AuthenticatorInterface;
use erdiko\authenticate\UserStorageInterface;

class JWTAuthenticator implements AuthenticatorInterface
{
	use \erdiko\authenticate\traits\ConfigLoaderTrait;
	use \erdiko\authenticate\traits\BuilderTrait;

	private $config;
	private $container;
	private $selectedStorage;

	protected $erdikoUser;

    /**
     * __construct
     *
     */
	public function __construct(UserStorageInterface $user)
	{
		$this->erdikoUser = $user;
		$this->container = new \Pimple\Container();
		$this->config = $this->loadFromJson();
		// Storage
		$this->selectedStorage = $this->config["storage"]["selected"];
		$storage = $this->config["storage"]["storage_types"];
		$this->buildStorages($storage);
		// Authentications
		$authentication = $this->config["authentication"]["available_types"];
		$this->buildAuthenticator($authentication);
	}

    /**
     * persistUser
     */
	public function persistUser(UserStorageInterface $user) { }

    /**
     * current_user
     */
	public function currentUser() { }

    /**
     * logout
     */
	public function logout() { }

	/**
     * login
     *
     * Attempt to log the user in via service model
     *
     */
	public function login($credentials = array(), $type = 'jwt_auth')
	{
		$storage = $this->container["STORAGES"][$this->selectedStorage];
		$result = false;

		// checks if it's already logged in
		$user = $storage->attemptLoad($this->erdikoUser);
		if($user instanceof iErdikoUser) {
			$this->logout();
		}

		$auth = $this->container["AUTHENTICATIONS"][$type];
		$result = $auth->login($credentials);
		if(isset($result->user))
			$user = $result->user;
		else
        	throw new \Exception("User failed to load");

        if(!empty($user) && (false !== $user)) {
        	$this->persistUser( $user );
        	$response = true;
        }

		return $result;
	}

    /**
     * verify
     *
     * Decode the JWT via the service model
     *
     */
    public function verify($credentials, $type = 'jwt_auth')
    {
		$result = false;
		try {
			$auth = $this->container["AUTHENTICATIONS"][$type];
            $result = $auth->verify($credentials);
		} catch (\Exception $e) {
			\error_log($e->getMessage());
		}
		return $result;
    }
}
