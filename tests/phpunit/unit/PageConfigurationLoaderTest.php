<?php

namespace GrowthExperiments\Tests;

use Collation;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\CampaignTopic;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use IContextSource;
use MalformedTitleException;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use Message;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use Title;
use TitleFactory;
use TitleParser;
use TitleValue;

/**
 * @coversDefaultClass  \GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader
 * FIXME these should be moved to the respective test classes
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler::validateTaskTypeConfiguration()
 * @covers \GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler::createTaskType()
 */
class PageConfigurationLoaderTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::loadTaskTypes
	 */
	public function testLoadTaskTypes() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockWikiPageConfigLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertIsArray( $taskTypes );
			$this->assertNotEmpty( $taskTypes );
			$this->assertInstanceOf( TaskType::class, $taskTypes[0] );
			$this->assertSame( [ 'copyedit', 'references', 'update' ], array_map( static function (
				TaskType $tt ) {
				return $tt->getId();
			}, $taskTypes ) );
			$this->assertSame( [ 'easy', 'medium', 'hard' ],
				array_map( static function ( TaskType $tt ) {
					return $tt->getDifficulty();
				}, $taskTypes )
			);
		}

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( StatusValue::newFatal( 'foo' ),
			[], PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertInstanceOf( StatusValue::class, $taskTypes );
			$this->assertTrue( $taskTypes->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTaskTypes
	 */
	public function testLoadTaskTypes_disabled() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$configurationLoader->disableTaskType( 'copyedit' );
		$this->assertArrayEquals( [ 'references', 'update' ],
			array_map( static function ( TaskType $tt ) {
				return $tt->getId();
			}, $configurationLoader->loadTaskTypes() )
		);

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$configurationLoader->disableTaskType( 'copyedit' );
		$configurationLoader->disableTaskType( 'references' );
		$configurationLoader->disableTaskType( 'update' );
		$this->assertArrayEquals( [], array_map( static function ( TaskType $tt ) {
			return $tt->getId();
		}, $configurationLoader->loadTaskTypes() ) );

		$disabledConfig = $this->getTaskConfig();
		$disabledConfig['copyedit']['disabled'] = true;
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $disabledConfig, [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$this->assertArrayEquals( [ 'references', 'update' ],
			array_map( static function ( TaskType $tt ) {
				return $tt->getId();
			}, $configurationLoader->loadTaskTypes() )
		);
	}

	/**
	 * @covers ::loadTaskTypes
	 * @dataProvider provideLoadTaskTypes_error
	 */
	public function testLoadTaskTypes_error( $error ) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'exists' )->willReturn( false );
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig( $error ), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
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
			[ 'invalidgroup' ],
			[ 'invalidtemplatetitle' ],
			[ 'missingmessage' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadOresTopics() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], $this->getOresTopicConfig(),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockWikiPageConfigLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertIsArray( $topics );
			$this->assertNotEmpty( $topics );
			$this->assertInstanceOf( Topic::class, $topics[0] );
			$this->assertSame( [ 'art', 'food', 'science' ], array_map( static function ( Topic $t ) {
				return $t->getId();
			}, $topics ) );
			$this->assertSame( [ [ 'music', 'painting' ], [ 'food' ], [ 'physics', 'biology' ] ],
				array_map( static function ( OresBasedTopic $t ) {
					return $t->getOresTopics();
				}, $topics ) );
		}

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], StatusValue::newFatal( 'foo' ),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertInstanceOf( StatusValue::class, $topics );
			$this->assertTrue( $topics->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTopics
	 * @dataProvider provideLoadOresTopics_error
	 */
	public function testLoadOresTopics_error( $error ) {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], $this->getOresTopicConfig( $error ),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$status = $configurationLoader->loadTopics();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadOresTopics_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadMorelikeTopics() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], $this->getMorelikeTopicConfig(),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockWikiPageConfigLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertIsArray( $topics );
			$this->assertNotEmpty( $topics );
			$this->assertInstanceOf( Topic::class, $topics[0] );
			$this->assertSame( [ 'art', 'science' ], array_map( static function ( Topic $t ) {
				return $t->getId();
			}, $topics ) );
			// FIXME can't test this while the RawMessage hack is used
			// $this->assertSame( [ 'Art', 'Science' ], array_map( function ( Topic $t ) {
			//	return $t->getName( $this->getMockMessageLocalizer() );
			// }, $topics ) );
			$this->assertSame( [ [ 'Music', 'Painting' ], [ 'Physics', 'Biology' ] ],
				array_map( static function ( MorelikeBasedTopic $t ) {
					return array_map( static function ( LinkTarget $lt ) {
						return $lt->getText();
					}, $t->getReferencePages() );
				}, $topics ) );
		}

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], StatusValue::newFatal( 'foo' ),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertInstanceOf( StatusValue::class, $topics );
			$this->assertTrue( $topics->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTopics
	 * @dataProvider provideLoadMorelikeTopics_error
	 */
	public function testLoadMorelikeTopics_error( $error ) {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], $this->getMorelikeTopicConfig( $error ),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$status = $configurationLoader->loadTopics();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadMorelikeTopics_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadTopics_noLoader() {
		$taskTitle = new TitleValue( NS_MEDIAWIKI, 'TaskConfiguration' );
		$wikiPageConfigLoader = $this->getMockWikiPageConfigLoader( [ '8:TaskConfiguration' => [] ] );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$taskHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationLoader = new PageConfigurationLoader( $this->getMockTitleFactory( [], false ),
			$wikiPageConfigLoader, $configurationValidator, $taskHandlerRegistry, $taskTitle, null,
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$topics = $configurationLoader->loadTopics();
		$this->assertSame( [], $topics );
	}

	/**
	 * @coversNothing
	 */
	public function testLinkTitleArguments() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(),
			$this->getOresTopicConfig(), PageConfigurationLoader::CONFIGURATION_TYPE_ORES, [], true );
		$taskTypes = $configurationLoader->loadTaskTypes();
		$this->assertIsArray( $taskTypes );
		$this->assertNotEmpty( $taskTypes );
		$this->assertInstanceOf( TaskType::class, $taskTypes[0] );
		$this->assertSame( [ 'copyedit', 'references', 'update' ],
			array_map( static function ( TaskType $tt ) {
				return $tt->getId();
			}, $taskTypes )
		);

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(),
			$this->getOresTopicConfig(), PageConfigurationLoader::CONFIGURATION_TYPE_ORES, [], true );
		$topics = $configurationLoader->loadTopics();
		$this->assertIsArray( $topics );
		$this->assertNotEmpty( $topics );
		$this->assertInstanceOf( Topic::class, $topics[0] );
		$this->assertSame( [ 'art', 'food', 'science' ], array_map( static function ( Topic $t ) {
			return $t->getId();
		}, $topics ) );
	}

	public function testLoadTopics_campaign() {
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( [], $this->getOresTopicConfig(),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$configurationLoader->setCampaignConfigCallback( static function () {
			return new CampaignConfig( [
				'growth-glam-2022' => [
					'topics' => [ 'argentina' ],
					'pattern' => '/^growth-glam-2022$/'
				]
			], [
				'argentina' => 'growtharticletopic:argentina'
			] );
		} );
		$campaignTopic = $configurationLoader->loadTopics()[0];
		$this->assertInstanceOf( CampaignTopic::class, $campaignTopic );
	}

	/**
	 * Test configuration
	 * @param string|null $error
	 * @return array|int
	 */
	protected function getTaskConfig( $error = null ) {
		$config = [
			'copyedit' => [
				'icon' => 'articleCheck',
				'group' => 'easy',
				'templates' => [ 'Foo', 'Bar', 'Baz' ],
				'excludedTemplates' => [ 'Exclude1', 'Exclude2' ],
				'excludedCategories' => [ 'ExcludedCategory1', 'ExcludedCategory2' ]
			],
			'references' => [
				'icon' => 'references',
				'group' => 'medium',
				'templates' => [ 'R1', 'R2', 'R3' ],
				'excludedTemplates' => [ 'Exclude3', 'Exclude4' ],
				'excludedCategories' => [ 'ExcludedCategory1', 'ExcludedCategory2' ]
			],
			'update' => [
				'icon' => 'newspaper',
				'group' => 'hard',
				'templates' => [ 'U1', 'U2' ],
				'excludedTemplates' => [ 'Exclude3', 'Exclude4' ],
				'excludedCategories' => [ 'ExcludedCategory1', 'ExcludedCategory2' ]
			]
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			return [ '*' => [] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['references']['group'] );
		} elseif ( $error === 'invalidgroup' ) {
			$config['references']['group'] = 'hardest';
		} elseif ( $error === 'invalidtemplatetitle' ) {
			$config['references']['templates'][] = '<invalid>';
		} elseif ( $error === 'missingmessage' ) {
			$config['foo'] = [ 'icon' => 'foo', 'group' => 'hard', 'templates' => [ 'T' ] ];
		}
		return $config;
	}

	/**
	 * Test configuration
	 * @param string|null $error
	 * @return array|int
	 */
	protected function getOresTopicConfig( $error = null ) {
		$config = [
			'topics' => [
				'art' => [
					'group' => 'culture',
					'oresTopics' => [ 'music', 'painting' ],
				],
				'science' => [
					'group' => 'stem',
					'oresTopics' => [ 'physics', 'biology' ],
				],
				'food' => [
					'group' => 'culture',
					'oresTopics' => [ 'food' ],
				],
			],
			'groups' => [ 'culture', 'stem' ],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			$config['topics']['*'] = [ 'group' => 'test', 'oresTopics' => [ 'test' ] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['topics']['science']['group'] );
		}
		return $config;
	}

	/**
	 * Test configuration
	 * @param string|null $error
	 * @return array|int
	 */
	protected function getMorelikeTopicConfig( $error = null ) {
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
	 * @param string $topicType
	 * @param Message[] $customMessages
	 * @param bool $useTitleValues
	 * @return PageConfigurationLoader
	 */
	protected function getNewcomerTasksConfigurationLoader(
		$taskConfig, $topicConfig, $topicType, array $customMessages = [], $useTitleValues = false
	) {
		if ( $useTitleValues ) {
			$taskConfigTitle = new TitleValue( NS_MEDIAWIKI, 'TaskConfigPage' );
			$topicConfigTitle = new TitleValue( NS_MEDIAWIKI, 'TopicConfigPage' );
		} else {
			$taskConfigTitle = 'MediaWiki:TaskConfigPage';
			$topicConfigTitle = 'MediaWiki:TopicConfigPage';
		}
		$titleFactory = $this->getMockTitleFactory( [
			'MediaWiki:TaskConfigPage' => $this->getMockTitle( 'TaskConfigPage', NS_MEDIAWIKI ),
			'MediaWiki:TopicConfigPage' => $this->getMockTitle( 'TopicConfigPage', NS_MEDIAWIKI ),
		] );
		$messageLocalizer = $this->getMockMessageLocalizer( $customMessages );
		$collation = $this->getMockCollation();
		$configurationValidator = new ConfigurationValidator( $messageLocalizer, $collation,
			$this->getMockTitleParser() );
		$wikiPageConfigLoader = $this->getMockWikiPageConfigLoader( [
			'8:TaskConfigPage' => $taskConfig,
			'8:TopicConfigPage' => $topicConfig,
		] );
		$taskTypeHandlerRegistry = $this->getMockTaskTypeHandlerRegistry( $configurationValidator );
		return new PageConfigurationLoader( $titleFactory, $wikiPageConfigLoader, $configurationValidator,
			$taskTypeHandlerRegistry, $taskConfigTitle, $topicConfigTitle, $topicType );
	}

	/**
	 * @param Title[]|null $map Page name => title
	 * @param bool $allowOther
	 * @return TitleFactory|MockObject
	 */
	protected function getMockTitleFactory( array $map, bool $allowOther = true ) {
		$titleFactory = $this->createNoOpMock( TitleFactory::class, [ 'newFromText' ] );
		$titleFactory->method( 'newFromText' )->willReturnCallback(
			function ( string $titleText, int $defaultNamespace = 0 ) use ( $map, $allowOther ) {
				if ( array_key_exists( $titleText, $map ) ) {
					return $map[$titleText];
				} elseif ( $titleText === '<invalid>' ) {
					return null;
				} elseif ( $allowOther ) {
					return $this->getMockTitle( $titleText, $defaultNamespace );
				} else {
					$this->fail( 'unexpected title' );
				}
			} );
		return $titleFactory;
	}

	/**
	 * @param string $titleText
	 * @param int $namespace
	 * @return Title|MockObject
	 */
	protected function getMockTitle( string $titleText, int $namespace = 0 ) {
		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $namespace );
		$title->method( 'inNamespace' )->willReturnCallback(
			static function ( $inNamespace ) use ( $namespace ) {
				return $inNamespace === $namespace;
			} );
		$title->method( 'getDBkey' )->willReturn( $titleText );
		return $title;
	}

	/**
	 * @param Message[] $customMessages
	 * @return MessageLocalizer|MockObject
	 */
	protected function getMockMessageLocalizer( array $customMessages = [] ) {
		$localizer = $this->getMockBuilder( MessageLocalizer::class )
			->onlyMethods( [ 'msg' ] )
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
	 * @param array $map Map of title => JSON array or StatusValue, where title is in
	 *   stringified TitleValue format.
	 * @return WikiPageConfigLoader|MockObject
	 */
	protected function getMockWikiPageConfigLoader( $map ) {
		$loader = $this->createNoOpMock( WikiPageConfigLoader::class, [ 'load' ] );
		$loader->expects( $this->atMost( 1 ) )
			->method( 'load' )
			->willReturnCallback( function ( LinkTarget $configPage ) use ( $map ) {
				$title = $configPage->getNamespace() . ':' . $configPage->getDBkey();
				$this->assertArrayHasKey( $title, $map );
				return $map[$title];
			} );
		return $loader;
	}

	/**
	 * @param Message[] $customMessages
	 * @return IContextSource|MockObject
	 */
	protected function getMockContext( array $customMessages = [] ) {
		$context = $this->getMockBuilder( IContextSource::class )
			->onlyMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$context->method( 'msg' )->willReturnCallback( function ( $key ) use ( $customMessages ) {
			if ( isset( $customMessages[$key] ) ) {
				return $customMessages[$key];
			}
			return $this->getMockMessage( $key );
		} );
		return $context;
	}

	/**
	 * @return Collation|MockObject
	 */
	protected function getMockCollation() {
		$collation = $this->createNoOpMock( Collation::class, [ 'getSortKey' ] );
		$collation->method( 'getSortKey' )->willReturnArgument( 0 );
		return $collation;
	}

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @return TaskTypeHandlerRegistry|MockObject
	 */
	private function getMockTaskTypeHandlerRegistry( ConfigurationValidator $configurationValidator ) {
		$titleParser = $this->getMockTitleParser();
		$handler = $this->createMock( TemplateBasedTaskSubmissionHandler::class );
		$registry = $this->createMock( TaskTypeHandlerRegistry::class );
		$registry->method( 'has' )->willReturn( true );
		$registry->method( 'get' )->with( TemplateBasedTaskTypeHandler::ID )
			->willReturn( new TemplateBasedTaskTypeHandler( $configurationValidator, $handler, $titleParser ) );
		return $registry;
	}

	/**
	 * @return TitleParser|MockObject
	 */
	private function getMockTitleParser() {
		$titleParser = $this->createMock( TitleParser::class );
		$titleParser->method( 'parseTitle' )
			->willReturnCallback( function ( string $title, int $defaultNamespace ) {
				if ( $title === '<invalid>' ) {
					throw $this->createMock( MalformedTitleException::class );
				}
				return new TitleValue( $defaultNamespace, $title );
			} );
		return $titleParser;
	}

}
