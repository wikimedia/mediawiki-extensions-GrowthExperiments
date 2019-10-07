<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task;
use GrowthExperiments\NewcomerTasks\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MultipleIterator;
use MWHttpRequest;
use PHPUnit\Framework\MockObject\MockObject;
use RawMessage;
use Status;
use StatusValue;
use Title;
use TitleFactory;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester
 * @covers \GrowthExperiments\Util::getApiUrl
 * @covers \GrowthExperiments\Util::getIteratorFromTraversable
 */
class RemoteSearchTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 * @param array|StatusValue $httpResult1 Result of the first HTTP query (after JSON decoding)
	 *   or error status.
	 * @param array|null $httpResult2 Result of the second HTTP query (after JSON decoding)
	 *   or null if there was only one query.
	 * @param string[] $taskFilter
	 * @param int|null $limit
	 * @param TaskSet|StatusValue $expectedTaskSet
	 */
	public function testSuggest( $httpResult1, $httpResult2, $taskFilter, $limit, $expectedTaskSet ) {
		$user = new UserIdentityValue( 1, 'Foo', 1 );

		$requestFactory = $this->getMockRequestFactory(
			$httpResult1 instanceof StatusValue ? $httpResult1 : json_encode( $httpResult1 ),
			$httpResult2 ? json_encode( $httpResult2 ) : null );
		$titleFactory = $this->getMockTitleFactory();
		$taskTypes = array_merge(
			[ new TemplateBasedTaskType( 'copyedit', TaskType::DIFFICULTY_EASY, [] ) ],
			$httpResult2 ? [ new TemplateBasedTaskType( 'link', TaskType::DIFFICULTY_EASY, [] ) ] : []
		);
		$suggester = new RemoteSearchTaskSuggester( $requestFactory, $titleFactory,
			'https://example.com', $taskTypes, [] );
		$taskSet = $suggester->suggest( $user, $taskFilter, null, $limit );
		if ( $expectedTaskSet instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $taskSet );
			$this->assertEquals( $expectedTaskSet->getErrors(), $taskSet->getErrors() );
		} else {
			$this->assertInstanceOf( TaskSet::class, $taskSet );
			$this->assertSame( $expectedTaskSet->getOffset(), $taskSet->getOffset() );
			$this->assertSame( $expectedTaskSet->getTotalCount(), $taskSet->getTotalCount() );
			$this->assertSame( count( $expectedTaskSet ), count( $taskSet ) );
			$it = new MultipleIterator( MultipleIterator::MIT_NEED_ANY | MultipleIterator::MIT_KEYS_ASSOC );
			$it->attachIterator( Util::getIteratorFromTraversable( $expectedTaskSet ), 'expected' );
			$it->attachIterator( Util::getIteratorFromTraversable( $taskSet ), 'actual' );
			foreach ( $it as $key => $value ) {
				$this->assertSame( $key['expected'], $key['actual'] );
				$expected = $value['expected'];
				$actual = $value['actual'];
				$this->assertInstanceOf( Task::class, $expected );
				$this->assertInstanceOf( Task::class, $actual );
				/** @var $expected Task */
				/** @var $actual Task */
				$this->assertSame( $expected->getTaskType()->getId(), $actual->getTaskType()->getId() );
				$this->assertSame( $expected->getTitle()->getNamespace(), $actual->getTitle()->getNamespace() );
				$this->assertSame( $expected->getTitle()->getDBkey(), $actual->getTitle()->getDBkey() );
			}
		}
	}

	public function provideSuggest() {
		$copyedit = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$link = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		return [
			'success' => [
				'httpResult1' => [
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
				'httpResult2' => null,
				'taskFilter' => null,
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Bar' ) ),
				], 100, 0 ),
			],
			'multiple queries' => [
				'httpResult1' => [
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
				'httpResult2' => [
					'query' => [
						'search' => [
							[ 'ns' => 0, 'title' => 'Baz' ],
						],
						'searchinfo' => [
							'totalhits' => 50,
						],
					],
				],
				'taskFilter' => null,
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $link, new TitleValue( 0, 'Baz' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Bar' ) ),
				], 150, 0 ),
			],
			'limit' => [
				'httpResult1' => [
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
				'httpResult2' => [
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
				'taskFilter' => null,
				'limit' => 2,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $link, new TitleValue( 0, 'Baz' ) ),
				], 150, 0 ),
			],
			'task type filter' => [
				'httpResult1' => [
					'query' => [
						'search' => [
							[ 'ns' => 0, 'title' => 'Foo' ],
						],
						'searchinfo' => [
							'totalhits' => 100,
						],
					],
				],
				'httpResult2' => [
					'query' => [
						'search' => [
							[ 'ns' => 0, 'title' => 'Bar' ],
						],
						'searchinfo' => [
							'totalhits' => 50,
						],
					],
				],
				'taskFilter' => [ 'copyedit' ],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
				], 100, 0 ),
			],
			'http error' => [
				'httpResult1' => StatusValue::newFatal( 'foo' ),
				'httpResult2' => null,
				'taskFilter' => null,
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( 'foo' ),
			],
			'api error' => [
				'httpResult1' => [
					'errors' => [
						[ 'text' => 'foo' ],
					],
				],
				'httpResult2' => null,
				'taskFilter' => null,
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( new RawMessage( 'foo' ) ),
			],
		];
	}

	/**
	 * @param string|Status $result1 A content string or an error status.
	 * @param string|null $result2 Content string for second HTTP request, optional
	 * @return HttpRequestFactory|MockObject
	 */
	protected function getMockRequestFactory( $result1, $result2 = null ) {
		if ( $result1 instanceof StatusValue ) {
			$status = Status::wrap( $result1 );
			$result1 = $result2 = null;
		} else {
			$status = Status::newGood();
		}

		$request1 = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->setMethods( [ 'execute', 'getContent' ] )
			->getMock();
		$request1->method( 'execute' )->willReturn( $status );
		$request1->method( 'getContent' )->willReturn( $result1 );

		$request2 = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->setMethods( [ 'execute', 'getContent' ] )
			->getMock();
		$request2->method( 'execute' )->willReturn( $status );
		$request2->method( 'getContent' )->willReturn( $result2 );

		$requestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );
		$requestFactory->expects( $this->atMost( $result2 && $status->isOK() ? 2 : 1 ) )
			->method( 'create' )
			->willReturnOnConsecutiveCalls( $request1, $request2 );
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

}
