<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Tracker\CacheStorage;
use GrowthExperiments\NewcomerTasks\Tracker\Tracker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentityValue;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\Tracker\Tracker
 */
class TrackerTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			Tracker::class,
			new Tracker(
				new CacheStorage(
					new \EmptyBagOStuff(),
					new UserIdentityValue( 1, 'Foo', 0 )
				),
				new \TitleFactory(),
				LoggerFactory::getInstance( 'test' )
			)
		);
	}

	/**
	 * @covers ::getTitleUrl
	 * @covers ::track
	 */
	public function testGetTitleUrl() {
		$titleFactoryMock = $this->getMockBuilder( \TitleFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();
		$titleMock->method( 'getLinkUrl' )
			->willReturn( 'https://foo' );
		$titleFactoryMock->method( 'newFromID' )
			->willReturn( $titleMock );
		$tracker = new Tracker(
			new CacheStorage(
				new \EmptyBagOStuff(),
				new UserIdentityValue( 1, 'Foo', 0 )
			),
			$titleFactoryMock,
			LoggerFactory::getInstance( 'test' )
		);
		$tracker->track( 42 );
		$this->assertEquals( 'https://foo', $tracker->getTitleUrl() );
	}

	/**
	 * @covers ::track
	 * @covers ::getTitleUrl
	 */
	public function testTrackFailure() {
		$titleFactoryMock = $this->getMockBuilder( \TitleFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$titleFactoryMock->method( 'newFromID' )
			->willReturn( null );
		$tracker = new Tracker(
			new CacheStorage(
				new \EmptyBagOStuff(),
				new UserIdentityValue( 1, 'Foo', 0 )
			),
			$titleFactoryMock,
			LoggerFactory::getInstance( 'test' )
		);
		$result = $tracker->track( 42 );
		$this->assertInstanceOf( \StatusValue::class, $result );
		$this->assertFalse( $result->isOK() );
	}

	/**
	 * @covers ::getTrackedPageIds
	 * @covers ::track
	 */
	public function testGetTrackedPageIds() {
		$titleFactoryMock = $this->getMockBuilder( \TitleFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$titleMock = $this->getMockBuilder( \Title::class )
			->disableOriginalConstructor()
			->getMock();
		$titleFactoryMock->method( 'newFromID' )
			->willReturn( $titleMock );
		$cacheStorageMock = $this->getMockBuilder( CacheStorage::class )
			->disableOriginalConstructor()
			->getMock();
		$cacheStorageMock->method( 'get' )
			->willReturn( [ 42 ] );
		$tracker = new Tracker(
			$cacheStorageMock,
			$titleFactoryMock,
			LoggerFactory::getInstance( 'test' )
		);
		$this->assertEquals( [ 42 ], $tracker->getTrackedPageIds() );
	}

}
