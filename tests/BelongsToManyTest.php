<?php

use Tests\User;
use Tests\Role;
use Illuminate\Support\Collection;
use ProxyManager\Proxy\ProxyInterface;
use Analogue\ORM\System\Proxies\CollectionProxy;
use Analogue\ORM\EntityCollection;

class BelongsToManyTest extends MongoTestCase
{
	/** @test */
	public function we_can_store_without_related_entities()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$this->mapper(User::class)->store($user);
		$this->seeInDatabase('users', [
			'email' => "test@example.com",
			'role_ids' => []
		]);
	}


	/** @test */
	public function we_can_store_a_related_entity()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);

		$this->seeInDatabase('users', [
			'role_ids' => [
				$roleA->_id,
				$roleB->_id,
			],
		]);
	}

	/** @test */
	public function we_can_eager_load_a_belongs_to_many_related_entity()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;	
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$this->clearCache();
		$loadedUser = $mapper->with('roles')->where("_id", "=", $user->_id)->first();
		$this->assertNotInstanceOf(ProxyInterface::class, $loadedUser->roles);
		$this->assertNotInstanceOf(CollectionProxy::class, $loadedUser->roles);
		$this->assertInstanceOf(EntityCollection::class, $loadedUser->roles);
		$this->assertCount(2, $loadedUser->roles);

	}

	/** @test */
	public function we_can_lazy_load_a_belongs_to_many_related_entity()
	{
		//$this->logQueries();
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$this->clearCache();
		$loadedUser = $mapper->find($user->_id);
		$this->assertInstanceOf(ProxyInterface::class, $loadedUser->roles);
		$this->assertInstanceOf(CollectionProxy::class, $loadedUser->roles);
		$this->assertCount(2, $loadedUser->roles);
		
	}

	/** @test */
	public function a_dirty_related_entity_is_updated_on_store()
	{
		//$this->logQueries();
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		
		$loadedUser = $mapper->find($user->_id);
		$loadedUser->roles->first()->name="New Role";
		$mapper->store($loadedUser);

		$this->seeInDatabase('roles', [
			'name' => 'New Role',
		]);
	}

	/** @test */
	public function setting_a_relationship_attribute_to_null_set_foreign_keys_to_an_empty_array()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$this->clearCache();
		$loadedUser = $mapper->find($user->_id);
		$loadedUser->roles = null;
		$mapper->store($loadedUser);
		$this->seeInDatabase('users', [
			'_id' => $user->_id,
			'email' => 'test@example.com',
			'role_ids' => [],
		]);


	}

	/** @test */
	public function non_loaded_collection_proxy_foreign_keys_are_not_overwritten_on_store()
	{
		//$this->logQueries();
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$loadedUser = $mapper->find($user->_id);
		setTddOn();
		$mapper->store($loadedUser);

		$this->seeInDatabase('users', [
			'_id' => $user->_id,
			'role_ids' => [
				$roleA->_id,
				$roleB->_id,
			],
		]);
	}

	/** @test */
	public function new_attributes_on_an_existing_relationship_are_added()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$user->roles->map(function($role) {
			$role->some_attribute = "1234";
		});
		$mapper->store($user);
		$this->seeInDatabase('roles', [
			'some_attribute' => "1234",
		]);

	}

	/** @test */
	public function existing_dirty_attributes_on_an_existing_relationship_are_updated()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$user->roles->map(function($role) {
			$role->some_attribute = "1234";
		});
		$mapper->store($user);
		$this->seeInDatabase('roles', [
			'some_attribute' => "1234",
		]);
		$user->roles->map(function($role) {
			$role->some_attribute = "3456";
		});
		$mapper->store($user);
		$this->seeInDatabase('roles', [
			'some_attribute' => "3456",
		]);
	}

	/** @test */
	public function dirty_relationship_on_an_existing_relationship_is_updated()
	{
		$user = new User;
		$user->email = 'test@example.com';
		$roleA = new Role;
		$roleA->name = "Role A";
		$roleB = new Role;
		$roleB->name = "Role B";
		$user->roles->push($roleA);
		$user->roles->push($roleB);
		$mapper = $this->mapper($user);
		$mapper->store($user);
		$user->roles->map(function($role) use ($user) {
			$role->creator_id = $user->_id;
		});
		$mapper->store($user);
		$this->seeInDatabase('roles', [
			'creator_id' => $user->_id,
		]);
	}
}