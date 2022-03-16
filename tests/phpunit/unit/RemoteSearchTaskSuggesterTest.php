<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use LinkBatch;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\Matcher\Invocation as InvocationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Status;
use StatusValue;
use Title;
use TitleFactory;
use TitleParser;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy
 * @covers \GrowthExperiments\Util::getApiUrl
 * @covers \GrowthExperiments\Util::getIteratorFromTraversable
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler::createTaskFromSearchResult
 */
class RemoteSearchTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 * @param array[] $taskTypeSpec All configured task types on the server. See getTaskTypes().
	 * @param array[] $topicSpec All configured topics on the server. See getTopics().
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @param TaskSetFilters $taskSetFilters
	 * @param int|null $limit
	 * @param TaskSet|StatusValue $expectedTaskSet
	 */
	public function testSuggest(
		$taskTypeSpec, $topicSpec, $requests, $taskSetFilters, $limit, $expectedTaskSet
	) {
		$user = new UserIdentityValue( 1, 'Foo' );
		$taskTypes = $this->getTaskTypes( $taskTypeSpec );
		$topics = $this->getTopics( $topicSpec );

		$taskTypeHandlerRegistry = $this->getMockTaskTypeHandlerRegistry();
		$searchStrategy = $this->getMockSearchStrategy( $taskTypeHandlerRegistry );
		$newcomerTasksUserOptionsLookup = $this->getNewcomerTasksUserOptionsLookup();
		$linkBatchFactory = $this->getMockLinkBatchFactory();
		$requestFactory = $this->getMockRequestFactory( $requests );
		$titleFactory = $this->getMockTitleFactory();

		$suggester = new RemoteSearchTaskSuggester( $taskTypeHandlerRegistry, $searchStrategy,
			$newcomerTasksUserOptionsLookup, $linkBatchFactory, $requestFactory, $titleFactory,
			'https://example.com', $taskTypes, $topics );

		$taskSet = $suggester->suggest( $user, $taskSetFilters, $limit );
		if ( $expectedTaskSet instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $taskSet );
			$this->assertEquals( $expectedTaskSet->getErrors(), $taskSet->getErrors() );
		} else {
			$this->assertInstanceOf( TaskSet::class, $taskSet );
			$this->assertSame( $expectedTaskSet->getOffset(), $taskSet->getOffset() );
			$this->assertSame( $expectedTaskSet->getTotalCount(), $taskSet->getTotalCount() );
			$this->assertCount( count( $expectedTaskSet ), $taskSet );
			// Responses are shuffled due to T242057 so we need order-insensitive comparison.
			$expectedTaskData = $this->taskSetToArray( $expectedTaskSet );
			$actualTaskData = $this->taskSetToArray( $taskSet );
			$this->assertArrayEquals( $expectedTaskData, $actualTaskData, false, false );
		}
	}

	public function provideSuggest() {
		$makeTask = static function ( TaskType $taskType, string $titleText, array $topicScores = [] ) {
			$task = new Task( $taskType, new TitleValue( NS_MAIN, $titleText ) );
			$task->setTopics( array_map( static function ( string $topicId ) {
				return new Topic( $topicId );
			}, array_keys( $topicScores ) ), $topicScores );
			return $task;
		};

		$copyedit = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$link = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		return [
			'success' => [
				// all configured task types on the server (see getTaskTypes() for format)
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				// all configured topics on the server (see getTopics() for format)
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				// expectations + response for each request the suggester should make
				'requests' => [
					[
						// a list of asserted query parameters (null means asserted to be not present)
						'params' => [
							'action' => 'query',
							'list' => 'search',
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
							'srnamespace' => '0',
						],
						// JSON data (in PHP form) or a StatusValue with errors
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
				],
				// parameters passed to the suggest() call
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => null,
				// expected return value from suggest()
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $copyedit, 'Bar' ),
				], 100, 0, new TaskSetFilters() ),
			],
			'multiple queries' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Bang' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $link, 'Bang' ),
					$makeTask( $copyedit, 'Bar' ),
					$makeTask( $copyedit, 'Baz' ),
					$makeTask( $copyedit, 'Boom' ),
				], 150, 0, new TaskSetFilters() ),
			],
			'limit' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srlimit' => '2',
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
					[
						'params' => [
							'srlimit' => '2',
							'srsearch' => 'hastemplate:"Link-1"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => 2,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $link, 'Baz' ),
				], 150, 0, new TaskSetFilters() ),
			],
			'task type filter' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters( [ 'copyedit' ], [] ),
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
				], 100, 0, new TaskSetFilters() ),
			],
			'topic filter' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2" morelikethis:"Music|Painting"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
								],
								'searchinfo' => [
									'totalhits' => 70,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2" morelikethis:"Physics|Biology"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Bar' ],
								],
								'searchinfo' => [
									'totalhits' => 30,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1" morelikethis:"Music|Painting"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1" morelikethis:"Physics|Biology"',
						],
						'response' => [
							'query' => [
								'search' => [],
								'searchinfo' => [
									'totalhits' => 0,
								],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters( [], [ 'art', 'science' ] ),
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					// scores are faked for now, so they are just ( 100 / position in response )
					$makeTask( $copyedit, 'Foo', [ 'art' => 100, ] ),
					$makeTask( $copyedit, 'Bar', [ 'science' => 100, ] ),
					$makeTask( $link, 'Baz', [ 'art' => 100 ] ),
					$makeTask( $link, 'Boom', [ 'art' => 50 ] ),
				], 150, 0, new TaskSetFilters() ),
			],
			'dedupe' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music' ], 'science' => [ 'Physics' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1" morelikethis:"Music"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'T1' ],
									[ 'ns' => 0, 'title' => 'T2' ],
									[ 'ns' => 0, 'title' => 'T3' ],
								],
								'searchinfo' => [
									'totalhits' => 25,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1" morelikethis:"Physics"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'T1' ],
									[ 'ns' => 0, 'title' => 'T4' ],
								],
								'searchinfo' => [
									'totalhits' => 25,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1" morelikethis:"Music"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'T2' ],
									[ 'ns' => 0, 'title' => 'T4' ],
									[ 'ns' => 0, 'title' => 'T5' ],
								],
								'searchinfo' => [
									'totalhits' => 25,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1" morelikethis:"Physics"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'T1' ],
									[ 'ns' => 0, 'title' => 'T5' ],
									[ 'ns' => 0, 'title' => 'T6' ],
								],
								'searchinfo' => [
									'totalhits' => 25,
								],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters( [], [ 'art', 'science' ] ),
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'T1', [ 'art' => 100, ] ),
					$makeTask( $copyedit, 'T2', [ 'art' => 100 / 2, ] ),
					$makeTask( $copyedit, 'T3', [ 'art' => 100 / 3, ] ),
					$makeTask( $copyedit, 'T4', [ 'science' => 100 / 2, ] ),
					$makeTask( $link, 'T5', [ 'art' => 100 / 3 ] ),
					$makeTask( $link, 'T6', [ 'science' => 100 / 3 ] ),
				], 100, 0, new TaskSetFilters() ),
			],
			'http error' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [],
						'response' => StatusValue::newFatal( 'foo' ),
					],
				],
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( 'foo' ),
			],
			'api error' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [],
						'response' => [
							'errors' => [
								[ 'text' => 'foo', 'code' => 'bar' ],
							],
						],
					],
				],
				'taskSetFilters' => new TaskSetFilters(),
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( new ApiRawMessage( 'foo', 'bar' ) ),
			],
		];
	}

	/**
	 * @dataProvider provideFilter
	 * @param array[] $taskTypeSpec All configured task types on the server. See getTaskTypes().
	 * @param array[] $topicSpec All configured topics on the server. See getTopics().
	 * @param TaskSet $taskSet
	 * @param array $pageIds Page IDs returned by the LinkBatch
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @param TaskSet|StatusValue $expectedTaskSet
	 */
	public function testFilter(
		array $taskTypeSpec,
		array $topicSpec,
		TaskSet $taskSet,
		array $pageIds,
		array $requests,
		$expectedTaskSet
	) {
		$user = new UserIdentityValue( 1, 'Foo' );
		$taskTypes = $this->getTaskTypes( $taskTypeSpec );
		$topics = $this->getTopics( $topicSpec );

		$taskTypeHandlerRegistry = $this->getMockTaskTypeHandlerRegistry();
		$searchStrategy = $this->getMockSearchStrategy( $taskTypeHandlerRegistry );
		$newcomerTasksUserOptionsLookup = $this->getNewcomerTasksUserOptionsLookup();
		$linkBatchFactory = $this->getMockLinkBatchFactory( $pageIds );
		$requestFactory = $this->getMockRequestFactory( $requests );
		$titleFactory = $this->getMockTitleFactory();

		$suggester = new RemoteSearchTaskSuggester( $taskTypeHandlerRegistry, $searchStrategy,
			$newcomerTasksUserOptionsLookup, $linkBatchFactory, $requestFactory, $titleFactory,
			'https://example.com', $taskTypes, $topics );

		$filteredTaskSet = $suggester->filter( $user, $taskSet );
		if ( $expectedTaskSet instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $filteredTaskSet );
			$this->assertEquals( $expectedTaskSet->getErrors(), $filteredTaskSet->getErrors() );
		} else {
			$this->assertInstanceOf( TaskSet::class, $filteredTaskSet );
			$this->assertSame( $expectedTaskSet->getOffset(), $filteredTaskSet->getOffset() );
			$this->assertSame( $expectedTaskSet->getTotalCount(), $filteredTaskSet->getTotalCount() );
			$this->assertCount( count( $expectedTaskSet ), $filteredTaskSet );
			for ( $i = 0; $i < count( $expectedTaskSet ); $i++ ) {
				$expectedTask = $expectedTaskSet[$i];
				$filteredTask = $filteredTaskSet[$i];
				$this->assertEquals( $expectedTask->getTaskType(), $filteredTask->getTaskType() );
				$this->assertSame( $expectedTask->getTitle()->getNamespace(),
					$filteredTask->getTitle()->getNamespace() );
				$this->assertSame( $expectedTask->getTitle()->getDBkey(),
					$filteredTask->getTitle()->getDBkey() );
				$this->assertEquals( $expectedTask->getTopics(), $filteredTask->getTopics() );
				$this->assertSame( $expectedTask->getTopicScores(), $filteredTask->getTopicScores() );
			}
		}
	}

	public function provideFilter() {
		return [
			'found' => [
				'taskTypes' => [ 'type1' => [ 'MaintTempl' ] ],
				'topics' => [],
				'taskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1' ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [] ) ),
				'pageids' => [ 1 ],
				'requests' => [ [
					'params' => [
						'srsearch' => 'hastemplate:"MaintTempl" pageid:1',
					],
					'response' => [
						'query' => [
							'search' => [
								[ 'ns' => 0, 'title' => 'Page1' ],
							],
							'searchinfo' => [
								'totalhits' => 10,
							],
						],
					],
				] ],
				'expectedTaskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1' ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [] ) ),
			],
			'not found' => [
				'taskTypes' => [ 'type1' => [ 'MaintTempl' ] ],
				'topics' => [],
				'taskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1' ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [] ) ),
				'pageids' => [ 1 ],
				'requests' => [ [
					'params' => [
						'srsearch' => 'hastemplate:"MaintTempl" pageid:1',
					],
					'response' => [
						'query' => [
							'search' => [],
							'searchinfo' => [
								'totalhits' => 10,
							],
						],
					],
				] ],
				'expectedTaskSet' => new TaskSet( [], 99, 0,
					new TaskSetFilters( [ 'type1' ], [] ) ),
			],
			'topic' => [
				'taskTypes' => [ 'type1' => [ 'MaintTempl' ] ],
				'topics' => [ 'topic1' => [ 'foo' ] ],
				'taskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1',
						[ 'topic1' => [ 'foo' ] ], [ 'topic1' => 0.8 ] ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [ 'topic1' ] ) ),
				'pageids' => [ 1 ],
				'requests' => [ [
					'params' => [
						'srsearch' => 'hastemplate:"MaintTempl" pageid:1',
					],
					'response' => [
						'query' => [
							'search' => [
								[ 'ns' => 0, 'title' => 'Page1' ],
							],
							'searchinfo' => [
								'totalhits' => 10,
							],
						],
					],
				] ],
				'expectedTaskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1',
						[ 'topic1' => [ 'foo' ] ], [ 'topic1' => 0.8 ] ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [] ) ),
			],
			'error' => [
				'taskTypes' => [ 'type1' => [ 'MaintTempl' ] ],
				'topics' => [],
				'taskSet' => new TaskSet( [
					$this->getTask( [ 'type1' => [ 'MaintTempl' ] ], 'Page1' ),
				], 100, 0, new TaskSetFilters( [ 'type1' ], [] ) ),
				'pageids' => [ 1 ],
				'requests' => [ [
					'params' => [],
					'response' => [
						'errors' => [
							[ 'text' => 'foo', 'code' => 'bar' ],
						],
					],
				] ],
				'expectedTaskSet' => StatusValue::newFatal( new ApiRawMessage( 'foo', 'bar' ) ),
			],
		];
	}

	/**
	 * @param array[] $taskTypeSpec Task type of the task. See getTaskTypes(); should have a single type.
	 * @param string $title Task page title (assumed to be in the mainspace).
	 * @param array[] $topicSpec Topics of the task. See getTopics().
	 * @param float[] $topicScores Topic ID => score.
	 * @return Task
	 */
	private function getTask( $taskTypeSpec, $title, $topicSpec = [], $topicScores = [] ) {
		$taskTypes = $this->getTaskTypes( $taskTypeSpec );
		$topics = $this->getTopics( $topicSpec );
		$task = new Task( $taskTypes[0], new TitleValue( NS_MAIN, $title ) );
		$task->setTopics( $topics, $topicScores );
		return $task;
	}

	/**
	 * @return TaskTypeHandlerRegistry|MockObject
	 */
	private function getMockTaskTypeHandlerRegistry() {
		$taskTypeHandlerRegistry = $this->createNoOpMock( TaskTypeHandlerRegistry::class,
			[ 'getByTaskType' ] );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$titleParser = $this->createNoOpMock( TitleParser::class );
		$handler = $this->createMock( TemplateBasedTaskSubmissionHandler::class );
		$taskTypeHandler = new TemplateBasedTaskTypeHandler(
			$configurationValidator,
			$handler,
			$titleParser
		);
		$taskTypeHandlerRegistry->method( 'getByTaskType' )->willReturn( $taskTypeHandler );
		return $taskTypeHandlerRegistry;
	}

	/**
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @return HttpRequestFactory|MockObject
	 */
	protected function getMockRequestFactory( array $requests ) {
		$requestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );

		$numRequests = count( $requests );
		$numErrors = count( array_filter( $requests, static function ( $request ) {
			return $request['response'] instanceof StatusValue;
		} ) );
		$expectation = $numErrors ? $this->exactlyBetween( 1, $numRequests - $numErrors + 1 )
			: $this->exactly( $numRequests );
		$requestFactory->expects( $expectation )
			->method( 'create' )
			->willReturnCallback( function ( $url ) use ( &$requests ) {
				$actualParams = wfCgiToArray( parse_url( $url )['query'] );
				$request = array_shift( $requests );
				foreach ( $request['params'] as $key => $expectedValue ) {
					if ( $expectedValue === null ) {
						$this->assertArrayNotHasKey( $key, $actualParams,
							"found URL parameter that should not have been present: $key "
							. "(with value >>$actualParams[$key]<<)" );
					} else {
						$this->assertArrayHasKey( $key, $actualParams, "expected URL parameter missing: $key" );
						$this->assertSame( $expectedValue, $actualParams[$key],
							"wrong URL parameter value for parameter $key: "
							. "expected >>$expectedValue<<, found >>$actualParams[$key]<<" );
					}
				}

				if ( $request['response'] instanceof StatusValue ) {
					$status = Status::wrap( $request['response'] );
					$response = '';
				} else {
					$status = StatusValue::newGood();
					$response = json_encode( $request['response'] );
				}

				$request = $this->getMockBuilder( MWHttpRequest::class )
					->disableOriginalConstructor()
					->onlyMethods( [ 'execute', 'getContent' ] )
					->getMock();
				$request->method( 'execute' )->willReturn( $status );
				$request->method( 'getContent' )->willReturn( $response );
				return $request;
			} );
		return $requestFactory;
	}

	/**
	 * @return TitleFactory|MockObject
	 */
	protected function getMockTitleFactory() {
		$titleFactory = $this->getMockBuilder( TitleFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'newFromText' ] )
			->getMock();
		$titleFactory->method( 'newFromText' )->willReturnCallback( function ( $dbKey, $ns ) {
			$title = $this->getMockBuilder( Title::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'getNamespace', 'getDBkey' ] )
				->getMock();
			$title->method( 'getNamespace' )->willReturn( $ns );
			$title->method( 'getDBkey' )->willReturn( $dbKey );
			return $title;
		} );
		return $titleFactory;
	}

	/**
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @return SearchStrategy|MockObject
	 */
	private function getMockSearchStrategy(
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	) {
		$searchStrategy = $this->getMockBuilder( SearchStrategy::class )
			->setConstructorArgs( [ $taskTypeHandlerRegistry ] )
			->onlyMethods( [ 'shuffleQueryOrder' ] )
			->getMock();
		$searchStrategy->method( 'shuffleQueryOrder' )
			->willReturnArgument( 0 );
		return $searchStrategy;
	}

	/**
	 * @return NewcomerTasksUserOptionsLookup|MockObject
	 */
	private function getNewcomerTasksUserOptionsLookup() {
		$lookup = $this->createNoOpMock( NewcomerTasksUserOptionsLookup::class, [ 'filterTaskTypes' ] );
		$lookup->method( 'filterTaskTypes' )->willReturnArgument( 0 );
		return $lookup;
	}

	/**
	 * @param array $pageIds Page IDs to return from LinkBatch::execute()
	 * @return LinkBatchFactory|MockObject
	 */
	private function getMockLinkBatchFactory( array $pageIds = [] ) {
		$linkBatchFactory = $this->createNoOpMock( LinkBatchFactory::class, [ 'newLinkBatch' ] );
		$linkBatch = $this->createNoOpMock( LinkBatch::class, [ 'execute' ] );
		$linkBatchFactory->method( 'newLinkBatch' )->willReturn( $linkBatch );
		$linkBatch->method( 'execute' )->willReturn( array_combine( $pageIds, $pageIds ) );
		return $linkBatchFactory;
	}

	/**
	 * @param array[] $spec [ task type id => [ title, ... ], ... ]
	 * @return TemplateBasedTaskType[]
	 */
	private function getTaskTypes( array $spec ) {
		$taskTypes = [];
		foreach ( $spec as $topicId => $titleNames ) {
			$titleValues = [];
			foreach ( $titleNames as $titleName ) {
				$titleValues[] = new TitleValue( NS_TEMPLATE, $titleName );
			}
			$taskTypes[] = new TemplateBasedTaskType( $topicId, TaskType::DIFFICULTY_EASY, [],
				$titleValues, [] );
		}
		return $taskTypes;
	}

	/**
	 * @param array[] $spec [ topic id => [ title, ... ], ... ]
	 * @return MorelikeBasedTopic[]
	 */
	private function getTopics( array $spec ) {
		$topics = [];
		foreach ( $spec as $topicId => $titleNames ) {
			$titleValues = [];
			foreach ( $titleNames as $titleName ) {
				$titleValues[] = new TitleValue( NS_MAIN, $titleName );
			}
			$topics[] = new MorelikeBasedTopic( $topicId, $titleValues );
		}
		return $topics;
	}

	/**
	 * Returns a PHPUnit invocation matcher which matches a range.
	 * @param int $min
	 * @param int $max
	 * @return InvokedBetween
	 */
	private function exactlyBetween( $min, $max ) {
		// CI uses PHPUnit 8.5 which requires expects() parameters to implement InvocationInterface.
		// The local package definition uses 7.5 where that interface does not exist. Yay.
		if ( interface_exists( InvocationInterface::class ) ) {
			return new class( $min, $max ) extends InvokedBetween implements InvocationInterface {
			};
		} else {
			return new InvokedBetween( $min, $max );
		}
	}

	private function taskSetToArray( TaskSet $taskSet ) {
		return array_map( static function ( Task $task ) {
			$taskData = [
				'taskType' => $task->getTaskType()->getId(),
				'titleNs' => $task->getTitle()->getNamespace(),
				'titleDbkey' => $task->getTitle()->getDBkey(),
				'topics' => $task->getTopicScores(),
			];
			return $taskData;
		}, iterator_to_array( Util::getIteratorFromTraversable( $taskSet ) ) );
	}

}
