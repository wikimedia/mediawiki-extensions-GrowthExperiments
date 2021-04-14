<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Tracker\CacheStorage;
use GrowthExperiments\NewcomerTasks\Tracker\Tracker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use TitleFactory;

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
					new UserIdentityValue( 1, 'Foo' )
				),
				$this->getMockConfigurationLoader( [] ),
				$this->getMockTitleFactory( [] ),
				new NullLogger()
			)
		);
	}

	/**
	 * @covers ::getTitleUrl
	 * @covers ::track
	 */
	public function testGetTitleUrl() {
		$tracker = new Tracker(
			new CacheStorage(
				new \EmptyBagOStuff(),
				new UserIdentityValue( 1, 'Foo' )
			),
			$this->getMockConfigurationLoader( [ 'type1' => new TaskType( 'type1', 'easy' ) ] ),
			$this->getMockTitleFactory( [ 42 => 'https://foo' ] ),
			LoggerFactory::getInstance( 'test' )
		);
		$tracker->track( 42, 'type1' );
		$this->assertEquals( 'https://foo', $tracker->getTitleUrl() );
	}

	/**
	 * @covers ::track
	 */
	public function testTrack() {
		$cacheStorageMock = $this->createNoOpMock( CacheStorage::class, [ 'set' ] );
		$cacheStorageMock->expects( $this->once() )
			->method( 'set' )
			->with( 42, 'type1' )
			->willReturn( true );
		$tracker = new Tracker(
			$cacheStorageMock,
			$this->getMockConfigurationLoader( [ 'type1' => new TaskType( 'type1', 'easy' ) ] ),
			$this->getMockTitleFactory( [ 42 => 'https://foo' ] ),
			new NullLogger()
		);
		$tracker->track( 42, 'type1', 'abcd' );
	}

	/**
	 * @covers ::track
	 * @covers ::getTitleUrl
	 */
	public function testTrackFailure() {
		$tracker = new Tracker(
			new CacheStorage(
				new \EmptyBagOStuff(),
				new UserIdentityValue( 1, 'Foo' )
			),
			$this->getMockConfigurationLoader( [ 'type1' => new TaskType( 'type1', 'easy' ) ] ),
			$this->getMockTitleFactory( [ 42 => 'https://foo' ] ),
			new NullLogger()
		);

		$result = $tracker->track( 40, 'type1' );
		$this->assertInstanceOf( \StatusValue::class, $result );
		$this->assertFalse( $result->isOK() );

		$result = $tracker->track( 42, 'type2' );
		$this->assertInstanceOf( \StatusValue::class, $result );
		$this->assertFalse( $result->isOK() );
	}

	/**
	 * @covers ::getTrackedPageIds
	 */
	public function testGetTrackedPageIds() {
		$cacheStorageMock = $this->createNoOpMock( CacheStorage::class, [ 'get' ] );
		$cacheStorageMock->method( 'get' )
			->willReturn( [ 42 => 'type1' ] );
		$tracker = new Tracker(
			$cacheStorageMock,
			$this->getMockConfigurationLoader( [ 'type1' => new TaskType( 'type1', 'easy' ) ] ),
			$this->getMockTitleFactory( [ 42 => 'https://foo' ] ),
			new NullLogger()
		);
		$this->assertEquals( [ 42 ], $tracker->getTrackedPageIds() );
	}

	/**
	 * @covers ::getTaskTypeForPage
	 */
	public function testGetTaskTypeForPage() {
		$taskType = new TaskType( 'type1', 'easy' );
		$cacheStorageMock = $this->createNoOpMock( CacheStorage::class, [ 'get' ] );
		$cacheStorageMock->method( 'get' )
			->willReturn( [ 42 => 'type1', 43 => null ] );
		$tracker = new Tracker(
			$cacheStorageMock,
			$this->getMockConfigurationLoader( [ 'type1' => $taskType ] ),
			$this->getMockTitleFactory( [ 42 => 'https://foo' ] ),
			new NullLogger()
		);
		$this->assertNull( $tracker->getTaskTypeForPage( 40 ) );
		$this->assertEquals( $taskType, $tracker->getTaskTypeForPage( 42 ) );
		$this->assertNull( $tracker->getTaskTypeForPage( 43 ) );
	}

	/**
	 * @param TaskType[] $taskTypes TaskType ID => TaskType
	 * @return ConfigurationLoader|MockObject
	 */
	private function getMockConfigurationLoader( $taskTypes ) {
		$configurationLoader = $this->createNoOpMock( ConfigurationLoader::class, [ 'getTaskTypes' ] );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( $taskTypes );
		return $configurationLoader;
	}

	/**
	 * @param array $titles
	 * @return TitleFactory|MockObject
	 */
	private function getMockTitleFactory( array $titles ) {
		$titleFactoryMock = $this->createNoOpMock( TitleFactory::class, [ 'newFromID' ] );
		$titleFactoryMock->method( 'newFromID' )
			->willReturnCallback( function ( int $id ) use ( $titles ) {
				$titleUrl = $titles[$id] ?? null;
				if ( !$titleUrl ) {
					return null;
				}
				$titleMock = $this->createNoOpMock( \Title::class, [ 'getLinkURL' ] );
				$titleMock->method( 'getLinkURL' )
					->willReturn( $titleUrl );
				return $titleMock;
			} );
		return $titleFactoryMock;
	}

}
