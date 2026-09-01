<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\InterestBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy
 * FIXME part of SearchStrategy is tested in RemoteSearchTaskSuggesterTest
 */
class SearchStrategyTest extends MediaWikiUnitTestCase {

	public function testGetQueries() {
		$taskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY,
			[], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ], [ new TitleValue( NS_TEMPLATE, 'DontCopyedit' ) ] );
		$oresTopic1 = new OresBasedTopic( 'art', 'culture', [ 'painting', 'drawing' ] );
		$oresTopic2 = new OresBasedTopic( 'science', 'stem', [ 'physics', 'biology' ] );
		$campaignTopic1 = new CampaignTopic( 'biology', 'hastemplate:Taxobox' );
		$campaignTopic2 = new CampaignTopic( 'argentina', 'hastemplate:Argentina' );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturn( 'hastemplate:"Copyedit" -hastemplate:"DontCopyedit"' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$oresQueries = $searchStrategy->getQueries( [ $taskType ], [ $oresTopic1, $oresTopic2 ], [] );
		$this->assertCount( 1, $oresQueries );
		$this->assertTaskTypeInQueries( $oresQueries, [ 'copyedit' ] );
		$this->assertTopicsInMultiTopicQueries( $oresQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $oresQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" articletopic:painting|drawing|physics|biology',
		] );

		$restrictedQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $oresTopic1, $oresTopic2 ], [ 1, 2, 3 ] );
		$this->assertCount( 1, $restrictedQueries );
		$this->assertTopicsInMultiTopicQueries( $restrictedQueries, [ 'art', 'science' ] );
		$this->assertQueryStrings( $restrictedQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" articletopic:painting|drawing|physics|biology ' .
			'pageid:1|2|3',
		] );

		$searchExpressionBasedTopicQueries = $searchStrategy->getQueries( [ $taskType ],
			[ $campaignTopic1, $campaignTopic2 ] );
		$this->assertCount( 2, $searchExpressionBasedTopicQueries );
		$this->assertTaskTypeInQueries( $searchExpressionBasedTopicQueries, [ 'copyedit' ] );
		$this->assertTopicsInQueries( $searchExpressionBasedTopicQueries, [ 'biology', 'argentina' ] );
		$this->assertQueryStrings( $searchExpressionBasedTopicQueries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" hastemplate:Taxobox',
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" hastemplate:Argentina',
		] );
	}

	public function testGetQueriesAll() {
		$taskType1 = new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'image-recommendation', TaskType::DIFFICULTY_MEDIUM );
		$oresTopic1 = new OresBasedTopic( 'literature', 'culture', [ 'literature', 'books' ] );
		$oresTopic2 = new OresBasedTopic( 'music', 'culture', [ 'music' ] );
		$campaignTopic = new CampaignTopic( 'argentina', 'growtharticle:argentina' );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturnOnConsecutiveCalls(
		'hasrecommendation:link',
				'hasrecommendation:image'
			);

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries(
			[ $taskType1, $taskType2 ],
			[ $campaignTopic, $oresTopic1, $oresTopic2 ],
			null,
			null,
			SearchStrategy::TOPIC_MATCH_MODE_AND
		);

		$this->assertCount( 2, $queries );
		$this->assertTaskTypeInQueries( $queries, [ 'link-recommendation', 'image-recommendation' ] );
		$this->assertIntersectionTopicsInQueries( $queries, [ 'literature', 'music', 'argentina' ] );
		$this->assertQueryStrings( $queries, [
			'hasrecommendation:image growtharticle:argentina articletopic:literature|books articletopic:music',
			'hasrecommendation:link growtharticle:argentina articletopic:literature|books articletopic:music',
		] );
	}

	public function testExclusion() {
		$excludedTemplates = [
			new TitleValue( NS_TEMPLATE, 'Foo' ),
			new TitleValue( NS_TEMPLATE, 'Bar' ),
		];
		$excludedCategories = [
			new TitleValue( NS_CATEGORY, 'Baz' ),
			new TitleValue( NS_CATEGORY, 'Boom' ),
		];
		$taskType = new TemplateBasedTaskType(
			'copyedit',
			TaskType::DIFFICULTY_EASY,
			[],
			[ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ],
			$excludedTemplates,
			$excludedCategories
		);
		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$titleParser = $this->createNoOpMock( TitleParser::class );
		$handler = $this->createMock( TemplateBasedTaskSubmissionHandler::class );
		$taskTypeHandler = new TemplateBasedTaskTypeHandler(
			$configurationValidator,
			$handler,
			$titleParser
		);
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType ], [] );
		$this->assertQueryStrings( $queries, [
			'-hastemplate:"Foo|Bar" -incategory:"Baz|Boom" hastemplate:"Copyedit"',
		] );
	}

	public function testGetQueriesForInterests() {
		$taskType = new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY,
			[], [ new TitleValue( NS_TEMPLATE, 'Copyedit' ) ], [ new TitleValue( NS_TEMPLATE, 'DontCopyedit' ) ] );
		$interest1 = new InterestBasedTopic( 'Albert Einstein',
			new TitleValue( NS_MAIN, 'Albert_Einstein' ) );
		$interest2 = new InterestBasedTopic( 'What Is "Life"?',
			new TitleValue( NS_MAIN, 'What_Is_"Life"?' ) );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturn( 'hastemplate:"Copyedit" -hastemplate:"DontCopyedit"' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType ], [ $interest1, $interest2 ] );
		$this->assertCount( 2, $queries );
		$this->assertTaskTypeInQueries( $queries, [ 'copyedit' ] );
		$this->assertTopicsInQueries( $queries, [ 'Albert Einstein', 'What Is "Life"?' ] );
		$this->assertQueryStrings( $queries, [
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" morelikethis:"Albert_Einstein"',
			'hastemplate:"Copyedit" -hastemplate:"DontCopyedit" morelikethis:"What_Is_\"Life\"\?"',
		] );
		$this->assertSortInQueries( $queries, 'relevance' );
		foreach ( $queries as $query ) {
			$this->assertNull( $query->getRescoreProfile() );
		}
	}

	public function testGetQueriesForInterestsPerTaskType() {
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'expand', TaskType::DIFFICULTY_MEDIUM );
		$interest1 = new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) );
		$interest2 = new InterestBasedTopic( 'Tea', new TitleValue( NS_MAIN, 'Tea' ) );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )
			->willReturnOnConsecutiveCalls(
				'hastemplate:"Copyedit"',
				'hastemplate:"Expand"'
			);

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType1, $taskType2 ], [ $interest1, $interest2 ] );
		$this->assertCount( 4, $queries );
		$this->assertTaskTypeInQueries( $queries, [ 'copyedit', 'expand' ] );
		$this->assertQueryStrings( $queries, [
			'hastemplate:"Copyedit" morelikethis:"Coffee"',
			'hastemplate:"Copyedit" morelikethis:"Tea"',
			'hastemplate:"Expand" morelikethis:"Coffee"',
			'hastemplate:"Expand" morelikethis:"Tea"',
		] );
		$this->assertSortInQueries( $queries, 'relevance' );
	}

	public function testGetQueriesForInterestsUnderlinked() {
		// Default settings have an underlinked weight of 0.5.
		$taskType = new LinkRecommendationTaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY );
		$interest1 = new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) );
		$interest2 = new InterestBasedTopic( 'Tea', new TitleValue( NS_MAIN, 'Tea' ) );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )->willReturn( 'hasrecommendation:link' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType ], [ $interest1, $interest2 ] );
		$this->assertCount( 2, $queries );
		$this->assertSortInQueries( $queries, 'relevance' );
		// Every per-interest query must carry the underlinked rescore profile, not just the last one.
		foreach ( $queries as $query ) {
			$this->assertSame( SearchQuery::RESCORE_UNDERLINKED, $query->getRescoreProfile() );
		}

		// With a page ID restriction, the rescore profile is not used.
		$restrictedQueries = $searchStrategy->getQueries( [ $taskType ], [ $interest1, $interest2 ],
			[ 1, 2, 3 ] );
		$this->assertCount( 2, $restrictedQueries );
		$this->assertQueryStrings( $restrictedQueries, [
			'hasrecommendation:link morelikethis:"Coffee" pageid:1|2|3',
			'hasrecommendation:link morelikethis:"Tea" pageid:1|2|3',
		] );
		$this->assertSortInQueries( $restrictedQueries, 'relevance' );
		foreach ( $restrictedQueries as $query ) {
			$this->assertNull( $query->getRescoreProfile() );
		}
	}

	public function testGetQueriesRejectsMixedInterests() {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$oresTopic = new OresBasedTopic( 'art', 'culture', [ 'painting' ] );
		$interest = new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$this->expectException( ParameterAssertionException::class );
		$searchStrategy->getQueries( [ $taskType ], [ $oresTopic, $interest ] );
	}

	public function testGetQueriesForInterestsIgnoresAndMode() {
		$taskType = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$interest1 = new InterestBasedTopic( 'Coffee', new TitleValue( NS_MAIN, 'Coffee' ) );
		$interest2 = new InterestBasedTopic( 'Tea', new TitleValue( NS_MAIN, 'Tea' ) );

		$taskTypeHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$taskTypeHandler = $this->createMock( TaskTypeHandler::class );
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		$taskTypeHandler->method( 'getSearchTerm' )->willReturn( 'hastemplate:"Copyedit"' );

		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );

		$queries = $searchStrategy->getQueries( [ $taskType ], [ $interest1, $interest2 ],
			null, null, SearchStrategy::TOPIC_MATCH_MODE_AND );
		$this->assertCount( 2, $queries );
		$this->assertArrayEquals( [ 'copyedit:Coffee', 'copyedit:Tea' ], array_keys( $queries ) );
		$this->assertQueryStrings( $queries, [
			'hastemplate:"Copyedit" morelikethis:"Coffee"',
			'hastemplate:"Copyedit" morelikethis:"Tea"',
		] );
		$this->assertSortInQueries( $queries, 'relevance' );
	}

	private function assertIntersectionTopicsInQueries( $queries, $topicIds ) {
		[ $query1, $query2 ] = array_values( $queries );
		foreach ( $topicIds as $id ) {
			$this->assertStringContainsString( $id, $query1->getQueryString() );
			$this->assertStringContainsString( $id, $query2->getQueryString() );
		}
	}

	private function assertTopicsInQueries( $queries, $topicIds ) {
		[ $query1, $query2 ] = array_values( $queries );
		foreach ( $topicIds as $id ) {
			if ( $query1->getTopics()[0]->getId() === $id ) {
				$this->assertSame( $query1->getTopics()[0]->getId(), $id );
			} elseif ( $query2->getTopics()[0]->getId() === $id ) {
				$this->assertSame( $query2->getTopics()[0]->getId(), $id );
			} else {
				$this->fail( "$id not found in query." );
			}
		}
	}

	private function assertTopicsInMultiTopicQueries( $queries, $topicIds ) {
		foreach ( $queries as $query ) {
			$actualTopicIds = array_reduce( array_values( $query->getTopics() ), static function ( $acc, $topic ) {
				$acc[] = $topic->getId();
				return $acc;
			}, [] );
			$diff = array_diff( $actualTopicIds, $topicIds );
			$this->assertSame( [], $diff );
		}
	}

	private function assertTaskTypeInQueries( $queries, $taskTypes ) {
		$actualTaskTypesIds = array_reduce( array_values( $queries ), static function ( $acc, $query ) {
			$acc[] = $query->getTaskType()->getId();
			return $acc;
		}, [] );
		$diff = array_diff( $actualTaskTypesIds, $taskTypes );
		$this->assertSame( [], $diff );
	}

	private function assertSortInQueries( $queries, $expectedSort ) {
		foreach ( $queries as $query ) {
			$this->assertSame( $expectedSort, $query->getSort() );
		}
	}

	/**
	 * Assert that each expected query string is present in the queries. Extra queries are
	 * not detected; pair with assertCount to check set equality.
	 * @param array $queries
	 * @param array $expectedQueryStrings
	 */
	private function assertQueryStrings( $queries, $expectedQueryStrings ) {
		$queryStrings = array_map( static function ( SearchQuery $query ) {
			return $query->getQueryString();
		}, array_values( $queries ) );
		foreach ( $expectedQueryStrings as $expectedQueryString ) {
			if ( !in_array( $expectedQueryString, $queryStrings, true ) ) {
				$this->fail( "$expectedQueryString not found in queries:\n"
					. var_export( $queryStrings, true ) );
			}
		}
		$this->assertTrue( true );
	}

}
