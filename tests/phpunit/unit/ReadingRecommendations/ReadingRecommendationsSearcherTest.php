<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearcher;
use GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearchResult;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Search\SearchEngine;
use MediaWiki\Search\SearchEngineFactory;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use StatusValue;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \GrowthExperiments\ReadingRecommendations\ReadingRecommendationsSearcher
 */
class ReadingRecommendationsSearcherTest extends MediaWikiUnitTestCase {

	public function testFindFeatured() {
		$titles = [ new TitleValue( NS_MAIN, 'Sun' ) ];
		$searchEngine = $this->getMockSearchEngine(
			'incategory:"Featured_articles"',
			$this->getResultSet( $titles )
		);
		$searchEngine->method( 'getValidSorts' )
			->willReturn( [ 'relevance', 'random' ] );
		$searchEngine->expects( $this->once() )
			->method( 'setSort' )
			->with( 'random' );
		$searchEngine->expects( $this->once() )
			->method( 'setFeatureData' )
			->with( 'random_seed', 42 );

		$searcher = $this->getSearcher( $searchEngine );
		$this->assertSearchResult(
			$titles,
			false,
			$searcher->findFeatured( new TitleValue( NS_CATEGORY, 'Featured_articles' ), 50, 42 )
		);
	}

	public function testFindFeaturedWithoutRandomSort() {
		$searchEngine = $this->getMockSearchEngine(
			'incategory:"Featured_articles"',
			$this->getResultSet( [] )
		);
		$searchEngine->method( 'getValidSorts' )
			->willReturn( [ 'relevance' ] );
		$searchEngine->expects( $this->never() )->method( 'setSort' );
		$searchEngine->expects( $this->never() )->method( 'setFeatureData' );

		$searcher = $this->getSearcher( $searchEngine );
		$this->assertSearchResult(
			[],
			false,
			$searcher->findFeatured( new TitleValue( NS_CATEGORY, 'Featured_articles' ), 50, 42 )
		);
	}

	public static function provideSearchTextOutcomes() {
		return [
			'wrapped in a good Status' => [ 'good-status', [ new TitleValue( NS_MAIN, 'Felidae' ) ], false ],
			'fatal status' => [ StatusValue::newFatal( 'search-error' ), [], true ],
			'search disabled (null)' => [ null, [], true ],
			'exception' => [ new RuntimeException( 'backend down' ), [], true ],
		];
	}

	/**
	 * @dataProvider provideSearchTextOutcomes
	 * @param mixed $searchResult
	 * @param TitleValue[] $expected
	 * @param bool $expectedError
	 */
	public function testSearchTextOutcomes( $searchResult, array $expected, bool $expectedError ) {
		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->method( 'getValidSorts' )->willReturn( [] );
		$searchText = $searchEngine->expects( $this->once() )->method( 'searchText' );
		if ( $searchResult instanceof RuntimeException ) {
			$searchText->willThrowException( $searchResult );
		} elseif ( $searchResult === 'good-status' ) {
			$searchText->willReturn( Status::newGood( $this->getResultSet( $expected ) ) );
		} else {
			$searchText->willReturn( $searchResult );
		}

		$searcher = $this->getSearcher( $searchEngine );
		$this->assertSearchResult(
			$expected,
			$expectedError,
			$searcher->findFeatured( new TitleValue( NS_CATEGORY, 'Featured_articles' ), 50, 42 )
		);
	}

	public function testNullSearchEngineFactory() {
		$searcher = new ReadingRecommendationsSearcher(
			null,
			StatsFactory::newNull(),
			new NullLogger()
		);
		$this->assertSearchResult(
			[],
			false,
			$searcher->findFeatured( new TitleValue( NS_CATEGORY, 'Featured_articles' ), 50, 42 )
		);
	}

	public function testFailureLogOmitsQuery() {
		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->method( 'getValidSorts' )->willReturn( [] );
		$searchEngine->method( 'searchText' )->willReturn( null );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with( 'ReadingRecommendationsSearcher: search failed', [
				'source' => 'featured',
				'status' => 'null',
			] );

		$result = $this->getSearcher( $searchEngine, $logger )
			->findFeatured( new TitleValue( NS_CATEGORY, 'Hidden_category' ), 50, 42 );

		$this->assertTrue( $result->isError() );
	}

	public function testExceptionLogOmitsQueryAndExceptionPayload() {
		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->method( 'getValidSorts' )->willReturn( [] );
		$searchEngine->method( 'searchText' )
			->willThrowException( new RuntimeException( 'Hidden category in exception message' ) );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with( 'ReadingRecommendationsSearcher: search threw', [
				'source' => 'featured',
				'exceptionClass' => RuntimeException::class,
			] );

		$result = $this->getSearcher( $searchEngine, $logger )
			->findFeatured( new TitleValue( NS_CATEGORY, 'Hidden_category' ), 50, 42 );

		$this->assertTrue( $result->isError() );
	}

	/**
	 * @param TitleValue[] $expectedTitles
	 */
	private function assertSearchResult(
		array $expectedTitles,
		bool $expectedError,
		ReadingRecommendationsSearchResult $result
	): void {
		$this->assertSame( $expectedTitles, $result->getTitles() );
		$this->assertSame( $expectedError, $result->isError() );
	}

	private function getSearcher(
		SearchEngine $searchEngine,
		?LoggerInterface $logger = null
	): ReadingRecommendationsSearcher {
		$factory = $this->createNoOpMock( SearchEngineFactory::class, [ 'create' ] );
		$factory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $searchEngine );
		return new ReadingRecommendationsSearcher(
			$factory,
			StatsFactory::newNull(),
			$logger ?? new NullLogger()
		);
	}

	/**
	 * @param TitleValue[] $titles
	 * @return ISearchResultSet|MockObject
	 */
	private function getResultSet( array $titles ) {
		$resultSet = $this->createMock( ISearchResultSet::class );
		$resultSet->method( 'extractTitles' )->willReturn( $titles );
		return $resultSet;
	}

	/**
	 * @param string $expectedQuery
	 * @param ISearchResultSet|MockObject $resultSet
	 * @return SearchEngine|MockObject
	 */
	private function getMockSearchEngine( string $expectedQuery, $resultSet ) {
		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->expects( $this->once() )
			->method( 'searchText' )
			->with( $expectedQuery )
			->willReturn( $resultSet );
		return $searchEngine;
	}
}
