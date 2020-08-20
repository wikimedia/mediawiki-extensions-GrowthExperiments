<?php

namespace GrowthExperiments\Tests;

use ApiRawMessage;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
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
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchQuery
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy
 * @covers \GrowthExperiments\Util::getApiUrl
 * @covers \GrowthExperiments\Util::getIteratorFromTraversable
 */
class RemoteSearchTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 * @param string[] $taskTypeSpec All configured task types on the server. See getTaskTypes().
	 * @param string[] $topicSpec All configured topics on the server. See getTopics().
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @param string[] $taskFilter
	 * @param string[] $topicFilter
	 * @param int|null $limit
	 * @param TaskSet|StatusValue $expectedTaskSet
	 */
	public function testSuggest(
		$taskTypeSpec, $topicSpec, $requests, $taskFilter, $topicFilter, $limit, $expectedTaskSet
	) {
		// FIXME null task/topic filter values are not tested, but they are not implemented anyway

		$templateProvider = $this->getMockTemplateProvider( $expectedTaskSet instanceof TaskSet );
		$requestFactory = $this->getMockRequestFactory( $requests );
		$titleFactory = $this->getMockTitleFactory();
		$searchStrategy = $this->getMockBuilder( SearchStrategy::class )
			->onlyMethods( [ 'shuffleQueryOrder' ] )
			->getMock();
		$searchStrategy->method( 'shuffleQueryOrder' )
			->willReturnArgument( 0 );

		$user = new UserIdentityValue( 1, 'Foo', 1 );
		$taskTypes = $this->getTaskTypes( $taskTypeSpec );
		$topics = $this->getTopics( $topicSpec );
		$suggester = new RemoteSearchTaskSuggester( $templateProvider, $searchStrategy, $requestFactory,
			$titleFactory, 'https://example.com', $taskTypes, $topics, [] );

		$taskSet = $suggester->suggest( $user, $taskFilter, $topicFilter, $limit );
		if ( $expectedTaskSet instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $taskSet );
			$this->assertEquals( $expectedTaskSet->getErrors(), $taskSet->getErrors() );
		} else {
			$this->assertInstanceOf( TaskSet::class, $taskSet );
			$this->assertSame( $expectedTaskSet->getOffset(), $taskSet->getOffset() );
			$this->assertSame( $expectedTaskSet->getTotalCount(), $taskSet->getTotalCount() );
			$this->assertSame( count( $expectedTaskSet ), count( $taskSet ) );
			// Responses are shuffled due to T242057 so we need order-insensitive comparison.
			$expectedTaskData = $this->taskSetToArray( $expectedTaskSet );
			$actualTaskData = $this->taskSetToArray( $taskSet );
			$this->assertArrayEquals( $expectedTaskData, $actualTaskData, false, false );
		}
	}

	public function provideSuggest() {
		$makeTask = function ( TaskType $taskType, string $titleText, array $topicScores = [] ) {
			$task = new Task( $taskType, new TitleValue( NS_MAIN, $titleText ) );
			$task->setTopics( array_map( function ( string $topicId ) {
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
				'taskFilter' => [],
				'topicFilter' => [],
				'limit' => null,
				// expected return value from suggest()
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $copyedit, 'Bar' ),
				], 100, 0 ),
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
				'taskFilter' => [],
				'topicFilter' => [],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $link, 'Bang' ),
					$makeTask( $copyedit, 'Bar' ),
					$makeTask( $copyedit, 'Baz' ),
					$makeTask( $copyedit, 'Boom' ),
				], 150, 0 ),
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
				'taskFilter' => [],
				'topicFilter' => [],
				'limit' => 2,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
					$makeTask( $link, 'Baz' ),
				], 150, 0 ),
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
				'taskFilter' => [ 'copyedit' ],
				'topicFilter' => [],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'Foo' ),
				], 100, 0 ),
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
				'taskFilter' => [],
				'topicFilter' => [ 'art', 'science' ],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					// scores are faked for now, so they are just ( 100 / position in response )
					$makeTask( $copyedit, 'Foo', [ 'art' => 100, ] ),
					$makeTask( $copyedit, 'Bar', [ 'science' => 100, ] ),
					$makeTask( $link, 'Baz', [ 'art' => 100 ] ),
					$makeTask( $link, 'Boom', [ 'art' => 50 ] ),
				], 150, 0 ),
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
				'taskFilter' => [],
				'topicFilter' => [ 'art', 'science' ],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					$makeTask( $copyedit, 'T1', [ 'art' => 100, ] ),
					$makeTask( $copyedit, 'T2', [ 'art' => 100 / 2, ] ),
					$makeTask( $copyedit, 'T3', [ 'art' => 100 / 3, ] ),
					$makeTask( $copyedit, 'T4', [ 'science' => 100 / 2, ] ),
					$makeTask( $link, 'T5', [ 'art' => 100 / 3 ] ),
					$makeTask( $link, 'T6', [ 'science' => 100 / 3 ] ),
				], 100, 0 ),
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
				'taskFilter' => [],
				'topicFilter' => [],
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
				'taskFilter' => [],
				'topicFilter' => [],
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( new ApiRawMessage( 'foo', 'bar' ) ),
			],
		];
	}

	/**
	 * @param bool $expectsToBeCalled
	 * @return TemplateProvider|MockObject
	 */
	private function getMockTemplateProvider( bool $expectsToBeCalled ) {
		$templateProvider = $this->getMockBuilder( TemplateProvider::class )
			->disableOriginalConstructor()
			->setMethods( [ 'fill' ] )
			->getMock();
		$templateProvider->expects( $expectsToBeCalled ? $this->once() : $this->never() )
			->method( 'fill' );
		return $templateProvider;
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
			->setMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );

		$numRequests = count( $requests );
		$numErrors = count( array_filter( $requests, function ( $request ) {
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
					->setMethods( [ 'execute', 'getContent' ] )
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
			->setMethods( [ 'newFromText' ] )
			->getMock();
		$titleFactory->method( 'newFromText' )->willReturnCallback( function ( $dbKey, $ns ) {
			$title = $this->getMockBuilder( Title::class )
				->disableOriginalConstructor()
				->setMethods( [ 'getNamespace', 'getDBkey' ] )
				->getMock();
			$title->method( 'getNamespace' )->willReturn( $ns );
			$title->method( 'getDBkey' )->willReturn( $dbKey );
			return $title;
		} );
		return $titleFactory;
	}

	/**
	 * @param string[] $spec [ task type id => [ title, ... ], ... ]
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
				$titleValues );
		}
		return $taskTypes;
	}

	/**
	 * @param string[] $spec [ topic id => [ title, ... ], ... ]
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
	 * @param $min
	 * @param $max
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
		return array_map( function ( Task $task ) {
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
