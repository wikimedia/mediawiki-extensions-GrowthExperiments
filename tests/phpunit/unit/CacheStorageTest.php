<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Tracker\CacheStorage;
use HashBagOStuff;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MWTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\Tracker\CacheStorage
 */
class CacheStorageTest extends MediaWikiUnitTestCase {

	protected function tearDown(): void {
		parent::tearDown();
		MWTimestamp::setFakeTime( false );
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			CacheStorage::class,
			new CacheStorage( new \EmptyBagOStuff(), new UserIdentityValue( 1, 'Foo' ) )
		);
	}

	/**
	 * @covers ::get
	 * @covers ::set
	 */
	public function testGetSet() {
		$cacheStorage = new CacheStorage(
			new HashBagOStuff(),
			new UserIdentityValue( 1, 'Foo' )
		);
		$this->assertSame( [], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 42, 'type1' ) );
		$this->assertSame( [ 42 => 'type1' ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 43, 'type2' ) );
		$this->assertArrayEquals( [ 42 => 'type1', 43 => 'type2' ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 43, 'type2' ) );
		$this->assertArrayEquals( [ 42 => 'type1', 43 => 'type2' ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 43, 'type3' ) );
		$this->assertArrayEquals( [ 42 => 'type1', 43 => 'type3' ], $cacheStorage->get() );
	}

	/**
	 * @covers ::get
	 * @covers ::set
	 */
	public function testExpiry() {
		$cacheStorage = new CacheStorage(
			new HashBagOStuff(),
			new UserIdentityValue( 1, 'Foo' )
		);
		MWTimestamp::setFakeTime( '2000-01-01 00:00:00' );
		$this->assertTrue( $cacheStorage->set( 42, 'type1' ) );
		MWTimestamp::setFakeTime( '2000-01-05 00:00:00' );
		$this->assertTrue( $cacheStorage->set( 43, 'type2' ) );
		$this->assertArrayEquals( [ 42 => 'type1', 43 => 'type2' ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-10 00:00:00' );
		$this->assertArrayEquals( [ 43 => 'type2' ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 44, 'type3' ) );
		$this->assertArrayEquals( [ 43 => 'type2', 44 => 'type3' ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-15 00:00:00' );
		$this->assertArrayEquals( [ 44 => 'type3' ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-20 00:00:00' );
		$this->assertArrayEquals( [], $cacheStorage->get() );
	}

}
