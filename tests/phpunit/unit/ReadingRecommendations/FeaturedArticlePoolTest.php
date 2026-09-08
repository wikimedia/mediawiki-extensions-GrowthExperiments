<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\FeaturedArticlePool;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearcher;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearchResult;
use GrowthExperiments\ReadingRecommendations\WikiDay;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\FeaturedArticlePool
 */
class FeaturedArticlePoolTest extends MediaWikiUnitTestCase {

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	public function testPoolIsCachedForTheDay() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$titles = [ new TitleValue( NS_MAIN, 'Sun' ) ];
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findFeatured' )
			->with(
				new TitleValue( NS_CATEGORY, 'Featured_articles' ),
				FeaturedArticlePool::POOL_SIZE,
				crc32( '2026-09-01' )
			)
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( $titles ) );
		$pool = $this->getPool( $searcher );

		$this->assertPoolResult( $titles, false, $pool->getPool( $this->getDay() ) );
		$this->assertPoolResult( $titles, false, $pool->getPool( $this->getDay() ) );
	}

	public function testNewDayTriggersANewSearch() {
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findFeatured' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( [
				new TitleValue( NS_MAIN, 'Sun' ),
			] ) );
		$pool = $this->getPool( $searcher );

		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$pool->getPool( $this->getDay() );
		ConvertibleTimestamp::setFakeTime( '2026-09-02T12:00:00Z' );
		$pool->getPool( $this->getDay() );
	}

	public function testChangingTheCategoryTriggersANewSearch() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findFeatured' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( [
				new TitleValue( NS_MAIN, 'Sun' ),
			] ) );

		$this->getPool( $searcher, 'Category:Featured articles', $wanCache )
			->getPool( $this->getDay() );
		$this->getPool( $searcher, 'Category:Good articles', $wanCache )
			->getPool( $this->getDay() );
	}

	public function testSuccessfulEmptyResultIsCached() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->once() )
			->method( 'findFeatured' )
			->willReturn( ReadingRecommendationsSearchResult::newSuccess( [] ) );
		$pool = $this->getPool( $searcher );

		$this->assertPoolResult( [], false, $pool->getPool( $this->getDay() ) );
		$this->assertPoolResult( [], false, $pool->getPool( $this->getDay() ) );
	}

	public function testSearchErrorIsCachedBriefly() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$cacheTime = microtime( true );
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$wanCache->setMockTime( $cacheTime );
		$searcher = $this->createMock( ReadingRecommendationsSearcher::class );
		$searcher->expects( $this->exactly( 2 ) )
			->method( 'findFeatured' )
			->willReturn( ReadingRecommendationsSearchResult::newError() );
		$pool = $this->getPool( $searcher, 'Category:Featured articles', $wanCache );

		$this->assertPoolResult( [], true, $pool->getPool( $this->getDay() ) );
		$cacheTime += 5 * WANObjectCache::TTL_MINUTE + 1;
		$this->assertPoolResult( [], true, $pool->getPool( $this->getDay() ) );
	}

	public function testEmptyConfigurationMeansNoSearch() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$searcher = $this->createNoOpMock( ReadingRecommendationsSearcher::class );
		$pool = $this->getPool( $searcher, '' );

		$this->assertPoolResult( [], false, $pool->getPool( $this->getDay() ) );
	}

	public function testInvalidCategoryMeansNoSearch() {
		ConvertibleTimestamp::setFakeTime( '2026-09-01T12:00:00Z' );
		$searcher = $this->createNoOpMock( ReadingRecommendationsSearcher::class );
		$pool = $this->getPool( $searcher, '<invalid>' );

		$this->assertPoolResult( [], false, $pool->getPool( $this->getDay() ) );
	}

	private function getDay(): WikiDay {
		return WikiDay::today( new ServiceOptions(
			WikiDay::CONSTRUCTOR_OPTIONS,
			[ MainConfigNames::Localtimezone => 'UTC' ]
		) );
	}

	/**
	 * @param ReadingRecommendationsSearcher|MockObject $searcher
	 * @param string $categoryConfig
	 * @param WANObjectCache|null $wanCache
	 * @return FeaturedArticlePool
	 */
	private function getPool(
		$searcher,
		string $categoryConfig = 'Category:Featured articles',
		?WANObjectCache $wanCache = null
	) {
		$titleParser = $this->createMock( TitleParser::class );
		// A mocked exception: the real constructor needs globals unit tests lack.
		$malformed = $this->createMock( MalformedTitleException::class );
		$titleParser->method( 'parseTitle' )->willReturnCallback( static function ( $text ) use ( $malformed ) {
			if ( $text === '<invalid>' ) {
				throw $malformed;
			}
			return new TitleValue( NS_CATEGORY, str_replace( [ 'Category:', ' ' ], [ '', '_' ], $text ) );
		} );
		return new FeaturedArticlePool(
			new ServiceOptions(
				FeaturedArticlePool::CONSTRUCTOR_OPTIONS,
				[ 'GEHomepageReadingRecommendationsFeaturedCategory' => $categoryConfig ]
			),
			$wanCache ?? new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$titleParser,
			$searcher,
			new NullLogger()
		);
	}

	/**
	 * @param TitleValue[] $expectedTitles
	 */
	private function assertPoolResult(
		array $expectedTitles,
		bool $expectedError,
		ReadingRecommendationsSearchResult $result
	): void {
		$this->assertEquals( $expectedTitles, $result->getTitles() );
		$this->assertSame( $expectedError, $result->isError() );
	}
}
