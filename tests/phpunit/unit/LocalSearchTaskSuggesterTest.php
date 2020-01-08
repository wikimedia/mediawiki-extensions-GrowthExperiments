<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use ISearchResultSet;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SearchEngine;
use SearchEngineFactory;
use Status;
use StatusValue;
use Wikimedia\TestingAccessWrapper;

/**
 * Most of the search functionality is shared with RemoteSearchTaskSuggester via the parent class
 * and covered in RemoteSearchTaskSuggesterTest. This class only tests the non-shared bits.
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester
 */
class LocalSearchTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSearch
	 * @covers ::__construct
	 * @covers ::search
	 * @param string $searchTerm
	 * @param int $limit
	 * @param int $offset
	 * @param ISearchResultSet|null|StatusValue $searchResults
	 * @param ISearchResultSet|StatusValue $expectedResult
	 */
	public function testSearch( $searchTerm, $limit, $offset, $searchResults, $expectedResult ) {
		$searchEngineFactory = $this->getMockSearchEngineFactory( $searchResults, $searchTerm,
			$limit, $offset );
		$templateProvider = $this->getMockTemplateProvider();
		$suggester = new LocalSearchTaskSuggester( $searchEngineFactory, $templateProvider, [], [], [] );
		$wrappedSuggester = TestingAccessWrapper::newFromObject( $suggester );
		$result = $wrappedSuggester->search( $searchTerm, $limit, $offset );
		if ( $expectedResult instanceof ApiRawMessage ) {
			$this->assertInstanceOf( StatusValue::class, $result );
			/** @var StatusValue $result */
			$this->assertNotEmpty( $result->getErrors() );
			$message = $result->getErrors()[0]['message'];
			$this->assertInstanceOf( ApiRawMessage::class, $message );
			/** @var ApiRawMessage $message */
			$this->assertSame( $expectedResult->getApiCode(), $message->getApiCode() );
		} else {
			$this->assertSame( $expectedResult, $result );
		}
	}

	public function provideSearch() {
		$searchResult = $this->getMockSearchResultSet();
		$error = Status::newFatal( 'foo' );
		return [
			'success' => [
				'search term' => 'hastemplate:foo morelikethis:bar',
				'limit' => 10,
				'offset' => 0,
				'search results' => $searchResult,
				'expected result' => $searchResult,
			],
			'success, wrapped' => [
				'search term' => 'hastemplate:foo morelikethis:bar',
				'limit' => 10,
				'offset' => 0,
				'search results' => Status::newGood( $searchResult ),
				'expected result' => $searchResult,
			],
			'error' => [
				'search term' => 'hastemplate:foo morelikethis:bar',
				'limit' => 10,
				'offset' => 0,
				'search results' => $error,
				'expected result' => $error,
			],
			'disabled' => [
				'search term' => 'hastemplate:foo morelikethis:bar',
				'limit' => 10,
				'offset' => 0,
				'search results' => null,
				'expected result' => new ApiRawMessage( '', 'grothexperiments-no-fulltext-search' ),
			],
		];
	}

	/**
	 * @return ISearchResultSet|MockObject
	 */
	private function getMockSearchResultSet() {
		return $this->getMockBuilder( ISearchResultSet::class )
			->setMethods( [] )
			->getMockForAbstractClass();
	}

	/**
	 * @param ISearchResultSet|null|StatusValue $searchResults
	 * @param string $expectedSearchText
	 * @param int $expectedLimit
	 * @param int $expectedOffset
	 * @return SearchEngineFactory|MockObject
	 */
	private function getMockSearchEngineFactory(
		$searchResults, $expectedSearchText, $expectedLimit, $expectedOffset
	) {
		$factory = $this->getMockBuilder( SearchEngineFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'create' ] )
			->getMockForAbstractClass();

		$searchEngine = $this->getMockBuilder( SearchEngine::class )
			->disableOriginalConstructor()
			->setMethods( [ 'setLimitOffset', 'setNamespaces', 'setShowSuggestion', 'getValidSorts',
				'setSort', 'searchText' ] )
			->getMockForAbstractClass();
		$factory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $searchEngine );

		$searchEngine->method( 'getValidSorts' )
			->willReturn( [ 'random' ] );
		$searchEngine->expects( $this->once() )
			->method( 'setLimitOffset' )
			->with( $expectedLimit, $expectedOffset );
		$searchEngine->expects( $this->once() )
			->method( 'searchText' )
			->with( $expectedSearchText )
			->willReturn( $searchResults );
		return $factory;
	}

	/**
	 * @return TemplateProvider|MockObject
	 */
	private function getMockTemplateProvider() {
		$templateProvider = $this->getMockBuilder( TemplateProvider::class )
			->disableOriginalConstructor()
			->setMethods( [ 'fill' ] )
			->getMock();
		return $templateProvider;
	}

}
