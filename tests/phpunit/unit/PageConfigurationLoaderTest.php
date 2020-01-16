<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use Message;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

/**
 * @coversDefaultClass  \GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader
 */
class PageConfigurationLoaderTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::loadTaskTypes
	 */
	public function testLoadTaskTypes() {
		$configurationLoader = $this->getConfigurationLoader( $this->getTaskConfig(), [] );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockPageLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertIsArray( $taskTypes );
			$this->assertNotEmpty( $taskTypes );
			$this->assertInstanceOf( TaskType::class, $taskTypes[0] );
			$this->assertSame( [ 'copyedit', 'references' ], array_map( function ( TaskType $tt ) {
				return $tt->getId();
			}, $taskTypes ) );
			$this->assertSame( [ 'easy', 'medium' ], array_map( function ( TaskType $tt ) {
				return $tt->getDifficulty();
			}, $taskTypes ) );
		}

		$configurationLoader = $this->getConfigurationLoader( StatusValue::newFatal( 'foo' ), [] );
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertInstanceOf( StatusValue::class, $taskTypes );
			$this->assertTrue( $taskTypes->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTaskTypes
	 * @dataProvider provideLoadTaskTypes_error
	 */
	public function testLoadTaskTypes_error( $error ) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'exists' )->willReturn( false );
		$configurationLoader = $this->getConfigurationLoader( $this->getTaskConfig( $error ), [],
			[ 'growthexperiments-homepage-suggestededits-tasktype-name-foo' => $msg ] );
		$status = $configurationLoader->loadTaskTypes();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadTaskTypes_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
			[ 'wronggroup' ],
			[ 'missingmessage' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadTopics() {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getTopicConfig() );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockPageLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertIsArray( $topics );
			$this->assertNotEmpty( $topics );
			$this->assertInstanceOf( Topic::class, $topics[0] );
			$this->assertSame( [ 'art', 'science' ], array_map( function ( Topic $t ) {
				return $t->getId();
			}, $topics ) );
			// FIXME can't test this while the RawMessage hack is used
			// $this->assertSame( [ 'Art', 'Science' ], array_map( function ( Topic $t ) {
			//	return $t->getName( $this->getMockMessageLocalizer() );
			// }, $topics ) );
			$this->assertSame( [ [ 'Music', 'Painting' ], [ 'Physics', 'Biology' ] ],
				array_map( function ( MorelikeBasedTopic $t ) {
					return array_map( function ( LinkTarget $lt ) {
						return $lt->getText();
					}, $t->getReferencePages() );
				}, $topics ) );
		}

		$configurationLoader = $this->getConfigurationLoader( [], StatusValue::newFatal( 'foo' ) );
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertInstanceOf( StatusValue::class, $topics );
			$this->assertTrue( $topics->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadTopics_noLoader() {
		$messageLocalizer = $this->getMockMessageLocalizer();
		$taskPageLoader = $this->getMockPageLoader( [] );
		$configurationLoader = new PageConfigurationLoader( $messageLocalizer, $taskPageLoader, null );
		$topics = $configurationLoader->loadTopics();
		$this->assertSame( [], $topics );
	}

	/**
	 * @covers ::loadTopics
	 * @dataProvider provideLoadTopics_error
	 */
	public function testLoadTopics_error( $error ) {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getTopicConfig( $error ) );
		$status = $configurationLoader->loadTopics();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadTopics_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
		];
	}

	/**
	 * @covers ::loadTemplateBlacklist
	 */
	public function testLoadTemplateBlacklist() {
		$this->markTestSkipped( 'Not implemented yet' );
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return array|int
	 */
	protected function getTaskConfig( $error = null ) {
		$config = [
			'copyedit' => [
				'icon' => 'articleCheck',
				'group' => 'easy',
				'templates' => [ 'Foo', 'Bar', 'Baz' ],
			],
			'references' => [
				'icon' => 'references',
				'group' => 'medium',
				'templates' => [ 'R1', 'R2', 'R3' ],
			],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			return [ '*' => [] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['references']['group'] );
		} elseif ( $error === 'wronggroup' ) {
			$config['references']['group'] = 'hardest';
		} elseif ( $error === 'missingmessage' ) {
			$config['foo'] = [ 'icon' => 'foo', 'group' => 'hard', 'templates' => [ 'T' ] ];
		}
		return $config;
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return array|int
	 */
	protected function getTopicConfig( $error = null ) {
		$config = [
			'art' => [
				'label' => 'Art',
				'titles' => [ 'Music', 'Painting' ],
			],
			'science' => [
				'label' => 'Science',
				'titles' => [ 'Physics', 'Biology' ],
			],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			return [ '*' => [] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['science']['titles'] );
		}
		return $config;
	}

	/**
	 * @param array|StatusValue $taskConfig
	 * @param array|StatusValue $topicConfig
	 * @param Message[] $customMessages
	 * @return PageConfigurationLoader
	 */
	protected function getConfigurationLoader(
		$taskConfig, $topicConfig, array $customMessages = []
	) {
		$messageLocalizer = $this->getMockMessageLocalizer( $customMessages );
		$taskPageLoader = $this->getMockPageLoader( $taskConfig );
		$topicPageLoader = $this->getMockPageLoader( $topicConfig );
		return new PageConfigurationLoader( $messageLocalizer, $taskPageLoader, $topicPageLoader );
	}

	/**
	 * @param Message[] $customMessages
	 * @return MessageLocalizer|MockObject
	 */
	protected function getMockMessageLocalizer( array $customMessages = [] ) {
		$localizer = $this->getMockBuilder( MessageLocalizer::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$localizer->method( 'msg' )
			->willReturnCallback( function ( $key, ...$params ) use ( $customMessages ) {
				if ( isset( $customMessages[$key] ) ) {
					return $customMessages[$key];
				}
				return $this->getMockMessage( $key, ...$params );
			} );
		return $localizer;
	}

	/**
	 * @param array|StatusValue $result
	 * @return PageLoader|MockObject
	 */
	protected function getMockPageLoader( $result ) {
		$loader = $this->getMockBuilder( PageLoader::class )
			->disableOriginalConstructor()
			->setMethods( [ 'load' ] )
			->getMock();
		$loader->expects( $this->atMost( 1 ) )
			->method( 'load' )
			->willReturn( $result );
		return $loader;
	}

	/**
	 * @param Message[] $customMessages
	 * @return IContextSource|MockObject
	 */
	protected function getMockContext( array $customMessages = [] ) {
		$context = $this->getMockBuilder( IContextSource::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$context->method( 'msg' )->willReturnCallback( function ( $key ) use ( $customMessages ) {
			if ( isset( $customMessages[$key] ) ) {
				return $customMessages[$key];
			}
			return $this->getMockMessage( $key );
		} );
		return $context;
	}

}
