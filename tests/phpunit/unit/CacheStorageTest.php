<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Tracker\CacheStorage;
use HashBagOStuff;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MWTimestamp;
use Wikimedia\TestingAccessWrapper;

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
			new CacheStorage( new \EmptyBagOStuff(), new UserIdentityValue( 1, 'Foo', 0 ) )
		);
	}

	/**
	 * @covers ::get
	 * @covers ::set
	 */
	public function testGetSet() {
		$cacheStorage = new CacheStorage(
			new HashBagOStuff(),
			new UserIdentityValue( 1, 'Foo', 0 )
		);
		$this->assertSame( [], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 42 ) );
		$this->assertSame( [ 42 ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 43 ) );
		$this->assertArrayEquals( [ 42, 43 ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 43 ) );
		$this->assertArrayEquals( [ 42, 43 ], $cacheStorage->get() );
	}

	/**
	 * @covers ::get
	 * @covers ::set
	 */
	public function testExpiry() {
		// this will only work after the cache format migration
		$this->markTestSkipped();
		$cacheStorage = new CacheStorage(
			new HashBagOStuff(),
			new UserIdentityValue( 1, 'Foo', 0 )
		);
		MWTimestamp::setFakeTime( '2000-01-01 00:00:00' );
		$this->assertTrue( $cacheStorage->set( 42 ) );
		MWTimestamp::setFakeTime( '2000-01-05 00:00:00' );
		$this->assertTrue( $cacheStorage->set( 43 ) );
		$this->assertArrayEquals( [ 42, 43 ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-10 00:00:00' );
		$this->assertArrayEquals( [ 43 ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 44 ) );
		$this->assertArrayEquals( [ 43, 44 ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-15 00:00:00' );
		$this->assertArrayEquals( [ 44 ], $cacheStorage->get() );
		MWTimestamp::setFakeTime( '2000-01-20 00:00:00' );
		$this->assertArrayEquals( [], $cacheStorage->get() );
	}

	/**
	 * Test that things work with the future data format [ page id => data ].
	 * This format will be added by a later patch, and the code needs to survive a rollback.
	 * @covers ::get
	 * @covers ::set
	 */
	public function testGetSetWithFutureData() {
		$bag = new HashBagOStuff();
		$cacheStorage = new CacheStorage(
			$bag,
			new UserIdentityValue( 1, 'Foo', 0 )
		);
		$cacheKey = TestingAccessWrapper::newFromObject( $cacheStorage )->getCacheKey();
		$bag->set( $cacheKey, [
			42 => [ 'type' => 'Foo', 'expires' => time() + 10000 ],
			43 => [ 'type' => 'Bar', 'expires' => time() + 10000 ],
		] );
		$this->assertEquals( [ 42, 43 ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 44 ) );
		$this->assertArrayEquals( [ 42, 43, 44 ], $cacheStorage->get() );
		$this->assertTrue( $cacheStorage->set( 45 ) );
		$this->assertArrayEquals( [ 42, 43, 44, 45 ], $cacheStorage->get() );
	}

}
