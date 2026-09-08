<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\FeaturedArticlePool;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendation;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearchResult;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsService;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\ReadingRecommendationsService
 */
class ReadingRecommendationsServiceTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
	}

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	public function testFillsAllSlotsFromTheFrontOfThePool() {
		$service = $this->newService( $this->titles( 'F1', 'F2', 'F3', 'F4', 'F5', 'F6' ) );

		$recommendations = $service->getRecommendations( new UserIdentityValue( 7, 'Alice' ) );

		$this->assertSame( [ 'F1', 'F2', 'F3', 'F4' ], $this->dbkeys( $recommendations ) );
		foreach ( $recommendations as $recommendation ) {
			$this->assertTrue( $recommendation->isGeneral() );
		}
	}

	public function testEveryUserGetsTheSameList() {
		$service = $this->newService( $this->titles( 'F1', 'F2', 'F3', 'F4', 'F5' ) );

		$this->assertSame(
			$this->dbkeys( $service->getRecommendations( new UserIdentityValue( 7, 'Alice' ) ) ),
			$this->dbkeys( $service->getRecommendations( new UserIdentityValue( 8, 'Bob' ) ) )
		);
	}

	public function testSmallPoolGivesAShorterList() {
		$service = $this->newService( $this->titles( 'F1', 'F2' ) );

		$this->assertSame(
			[ 'F1', 'F2' ],
			$this->dbkeys( $service->getRecommendations( new UserIdentityValue( 7, 'Alice' ) ) )
		);
	}

	public function testEmptyPoolGivesAnEmptyList() {
		$service = $this->newService( [] );

		$this->assertSame( [], $service->getRecommendations( new UserIdentityValue( 7, 'Alice' ) ) );
	}

	public function testPoolErrorGivesAnEmptyList() {
		$pool = $this->createMock( FeaturedArticlePool::class );
		$pool->method( 'getPool' )->willReturn( ReadingRecommendationsSearchResult::newError() );
		$service = $this->newService( [], $pool );

		$this->assertSame( [], $service->getRecommendations( new UserIdentityValue( 7, 'Alice' ) ) );
	}

	/**
	 * @return TitleValue[]
	 */
	private function titles( string ...$dbkeys ): array {
		return array_map( static fn ( string $dbkey ) => new TitleValue( NS_MAIN, $dbkey ), $dbkeys );
	}

	/**
	 * @param ReadingRecommendation[] $recommendations
	 * @return string[]
	 */
	private function dbkeys( array $recommendations ): array {
		return array_map(
			static fn ( ReadingRecommendation $recommendation ) => $recommendation->getTitle()->getDBkey(),
			$recommendations
		);
	}

	/**
	 * @param TitleValue[] $poolTitles
	 * @param FeaturedArticlePool|null $pool
	 */
	private function newService( array $poolTitles, ?FeaturedArticlePool $pool = null ): ReadingRecommendationsService {
		if ( !$pool ) {
			$pool = $this->createMock( FeaturedArticlePool::class );
			$pool->method( 'getPool' )->willReturn(
				ReadingRecommendationsSearchResult::newSuccess( $poolTitles )
			);
		}
		return new ReadingRecommendationsService(
			new ServiceOptions(
				ReadingRecommendationsService::CONSTRUCTOR_OPTIONS,
				[ MainConfigNames::Localtimezone => 'UTC' ]
			),
			$pool
		);
	}
}
