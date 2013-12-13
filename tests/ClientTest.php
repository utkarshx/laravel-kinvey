<?php namespace Govtribe\LaravelKinvey\Tests;

use GovTribe\LaravelKinvey\Facades\Kinvey;
use GovTribe\LaravelKinvey\Client\Exception\KinveyResponseException;

class ClientTest extends LaravelKinveyTestCase {

	/**
	 * Anonymous ping requests.
	 *
	 * @return void
	 */
	public function testPingAnon()
	{
		$response = Kinvey::pingAnon();

		$this->assertTrue(is_array($response), 'Response is array');
		$this->assertArrayHasKey('version', $response, 'Response has version key');
		$this->assertArrayHasKey('kinvey', $response, 'Response has kinvey key');
		$this->assertEquals('hello', $response['kinvey'], 'Kinvey key is equal to hello');
	}

	/**
	 * Authenticated ping requests.
	 *
	 * @return void
	 */
	public function testPingAuth()
	{
		$response = Kinvey::pingAuth(array('authMode' => 'app'));

		$this->assertTrue(is_array($response), 'Response is array');
		$this->assertArrayHasKey('version', $response, 'Response has version key');
		$this->assertArrayHasKey('kinvey', $response, 'Response has kinvey key');
		$this->assertEquals(
			'hello ' . Kinvey::getConfig('appName'), $response['kinvey'],
			'Kinvey key is equal to hello ' . Kinvey::getConfig('appName')
		);
	}

	/**
	 * User login and logout.
	 *
	 * @return void
	 */
	public function testKinveyLoginLogout()
	{
		$testUser = self::createTestUser();

		$userFromCredentials = Kinvey::login(array(
			'data' => array(
				'username'  => $testUser['username'],
				'password'  => $testUser['password'],
			)
		));
		$this->assertTrue(is_array($userFromCredentials), 'User retrieved is array');

		$userFromToken = Kinvey::me(array(
			'token' => $userFromCredentials['_kmd']['authtoken'],
			'authMode' 	=> 'session',
		));
		$this->assertTrue(is_array($userFromToken), 'User retrieved is array');

		$response = Kinvey::logout(array(
			'token' => $userFromCredentials['_kmd']['authtoken'],
			'authMode' 	=> 'session',
		));
		$this->assertEquals(204, $response->getStatusCode(), 'Logout OK');

		Kinvey::deleteEntity(array(
			'collection' => 'user',
			'_id' => $testUser['_id'],
			'hard' => 'true',
			'authMode' => 'admin',
		));
	}

	/**
	 * User suspend.
	 *
	 * @return void
	 */
	public function testKinveyUserSuspend()
	{
		$testUser = self::createTestUser();

		Kinvey::deleteEntity(array(
			'collection' => 'user',
			'_id' => $testUser['_id'],
			'hard' => 'false',
			'authMode' => 'admin',
		));

		$suspendedUser = Kinvey::retrieveEntity(array(
			'_id' => $testUser['_id'],
			'collection' => 'user',
			'authMode' => 'admin',
		));

		$this->assertEquals('disabled', $suspendedUser['_kmd']['status']['val'], 'User is suspended');

		Kinvey::deleteEntity(array(
			'collection' => 'user',
			'_id' => $testUser['_id'],
			'hard' => 'true',
			'authMode' => 'admin',
		));
	}

	/**
	 * Create entity.
	 *
	 * @return array
	 */
	public function testCreateEntity()
	{
		$testEntity = self::createTestEntity();

		$this->assertTrue(is_array($testEntity), 'Response is array');
		$this->assertArrayHasKey('foo', $testEntity, 'Response has foo key');
		$this->assertEquals('bar', $testEntity['foo'], 'foo key is equal to bar');

		self::deleteTestCollection();
	}

	/**
	 * Retrieve entity.
	 *
	 * @return void
	 */
	public function testRetrieveEntity()
	{
		$testEntity = self::createTestEntity();

		$command = Kinvey::getCommand('retrieveEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
		));
		$command->execute();

		$response = $command->getResponse();
		$this->assertEquals('200', $response->getStatusCode(), 'Got correct status code');

		self::deleteTestCollection();
	}

	/**
	 * Update Entity.
	 *
	 * @return void
	 */
	public function testUpdateEntity()
	{
		$testEntity = self::createTestEntity();

		$command = Kinvey::getCommand('updateEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
			'data' => $testEntity + array('new_key' => 'new_value'),
		));

		$response = $command->execute();
		$this->assertEquals('200', $command->getResponse()->getStatusCode(), 'Got correct status code');
		$this->assertTrue(is_array($response), 'Response is array');
		$this->assertArrayHasKey('new_key', $response, 'Response has new_key key');
		$this->assertEquals('new_value', $response['new_key'], 'new_key key is equal to new_value');

		self::deleteTestCollection();
	}

	/**
	 * Delete entity.
	 *
	 * @return void
	 */
	public function testDeleteEntity()
	{
		$testEntity = self::createTestEntity();

		// Delete the entity.
		$command = Kinvey::getCommand('deleteEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
		));

		$response = $command->execute();
		$this->assertEquals('200', $command->getResponse()->getStatusCode(), 'Got correct status code');
		$this->assertEquals(array('count' => 1), $response, 'Got correct status code');

		// Try to retrieve the deleted entity.
		$command = Kinvey::getCommand('retrieveEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
		));

		$ok = false;
		try
		{
			$command->execute();
		}
		catch (KinveyResponseException $e)
		{
			$this->assertEquals('404', $e->getResponse()->getStatusCode(), 'Got correct status code');
			$ok = true;
		}
		$this->assertTrue($ok, 'Entity was deleted');
		self::deleteTestCollection();
	}

	/**
	 * Upload file.
	 *
	 * @return void
	 */
	public function testUploadFile()
	{
		$testEntity = self::createTestEntity();

		// Delete the entity.
		$command = Kinvey::getCommand('deleteEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
		));

		$response = $command->execute();
		$this->assertEquals('200', $command->getResponse()->getStatusCode(), 'Got correct status code');
		$this->assertEquals(array('count' => 1), $response, 'Got correct status code');

		// Try to retrieve the deleted entity.
		$command = Kinvey::getCommand('retrieveEntity', array(
			'_id' => $testEntity['_id'],
			'collection' => 'widgets',
			'authMode' => 'admin',
		));

		$ok = false;
		try
		{
			$command->execute();
		}
		catch (KinveyResponseException $e)
		{
			$this->assertEquals('404', $e->getResponse()->getStatusCode(), 'Got correct status code');
			$ok = true;
		}
		$this->assertTrue($ok, 'Entity was deleted');
		self::deleteTestCollection();
	}


	/**
	 * Create a test entity.
	 *
	 * @return array
	 */
	public static function createTestEntity()
	{
		return Kinvey::createEntity(array(
			'collection' => 'widgets',
			'authMode' => 'admin',
			'data' => array(
				'foo' => 'bar',
			),
		));
	}

	/**
	 * Create a test user.
	 *
	 * @return array
	 */
	public static function createTestUser()
	{
		return Kinvey::createEntity(array(
			'collection' => 'user',
			'data' => array(
				'username'	=> 'test.guy@foo.com',
				'first_name'=> 'Test',
				'last_name' => 'Guy',
				'password' 	=> str_random(8),
			)
		));
	}

	/**
	 * Delete the test collection.
	 *
	 * @param  string $id
	 * @return void
	 */
	public static function deleteTestCollection()
	{
		$response = Kinvey::deleteCollection(array(
			'collection' => 'widgets',
			'authMode' => 'admin',
		));
	}
}