<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Tracker\CacheStorage;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\Tracker\CacheStorage
 */
class CacheStorageTest extends MediaWikiUnitTestCase {

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
	 * @covers ::set
	 * @covers ::getCacheKey
	 */
	public function testSetSuccess() {
		$cacheStorage = new CacheStorage(
			new \EmptyBagOStuff(),
			new UserIdentityValue( 1, 'Foo', 0 )
		);
		$this->assertTrue( $cacheStorage->set( 42 ) );
	}

	/**
	 * @covers ::set
	 * @covers ::get
	 * @covers ::getCacheKey
	 */
	public function testGet() {
		$cacheMock = $this->getMockBuilder( \BagOStuff::class )
			->disableOriginalConstructor()
			->getMock();
		$cacheMock->method( 'get' )
			->willReturn( [ 42 ] );
		$cacheMock->method( 'merge' )
			->willReturn( true );
		$cacheStorage = new CacheStorage(
			$cacheMock,
			new UserIdentityValue( 1, 'Foo', 0 )
		);
		$cacheStorage->set( 42 );
		$this->assertEquals( [ 42 ], $cacheStorage->get() );
	}

}
