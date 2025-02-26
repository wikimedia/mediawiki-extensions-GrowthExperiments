<?php

namespace GrowthExperiments\Tests\Unit;

use Collation;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\EmptyTopicRegistry;
use MediaWiki\Collation\CollationFactory;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

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
	 * @covers ::getTaskTypes
	 * @covers ::getDisabledTaskTypes
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
		$this->assertSame( array_values( $taskTypes ), array_values( $configurationLoader->getTaskTypes() ) );
		$this->assertArrayKeyMatchesTaskTypeId( $configurationLoader->getTaskTypes() );
		$this->assertSame( [], $configurationLoader->getDisabledTaskTypes() );

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( StatusValue::newFatal( 'foo' ),
			[], PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertInstanceOf( StatusValue::class, $taskTypes );
			$this->assertStatusError( 'foo', $taskTypes );
		}
		$this->assertSame( [], $configurationLoader->getTaskTypes() );
		$this->assertSame( [], $configurationLoader->getDisabledTaskTypes() );
	}

	/**
	 * @covers ::loadTaskTypes
	 * @covers ::getDisabledTaskTypes
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
		$this->assertArrayEquals( [ 'copyedit' ], array_keys( $configurationLoader->getDisabledTaskTypes() ) );
		$this->assertArrayKeyMatchesTaskTypeId( $configurationLoader->getDisabledTaskTypes() );

		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $this->getTaskConfig(), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$configurationLoader->disableTaskType( 'copyedit' );
		$configurationLoader->disableTaskType( 'references' );
		$configurationLoader->disableTaskType( 'update' );
		$this->assertArrayEquals( [], array_map( static function ( TaskType $tt ) {
			return $tt->getId();
		}, $configurationLoader->loadTaskTypes() ) );
		$this->assertArrayEquals( [ 'copyedit', 'references', 'update' ],
			array_keys( $configurationLoader->getDisabledTaskTypes() ) );
		$this->assertArrayKeyMatchesTaskTypeId( $configurationLoader->getDisabledTaskTypes() );

		$disabledConfig = $this->getTaskConfig();
		$disabledConfig['copyedit']['disabled'] = true;
		$configurationLoader = $this->getNewcomerTasksConfigurationLoader( $disabledConfig, [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$this->assertArrayEquals( [ 'references', 'update' ],
			array_map( static function ( TaskType $tt ) {
				return $tt->getId();
			}, $configurationLoader->loadTaskTypes() )
		);
		$this->assertArrayEquals( [ 'copyedit' ], array_keys( $configurationLoader->getDisabledTaskTypes() ) );
		$this->assertArrayKeyMatchesTaskTypeId( $configurationLoader->getDisabledTaskTypes() );
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
		$this->assertStatusError( 'growthexperiments-homepage-suggestededits-config-' . $error, $status );
	}

	public static function provideLoadTaskTypes_error() {
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
	public function testLoadTopics_noLoader() {
		$taskTitle = new TitleValue( NS_MEDIAWIKI, 'TaskConfiguration' );
		$wikiPageConfigLoader = $this->getMockWikiPageConfigLoader( [ '8:TaskConfiguration' => [] ] );
		$configurationValidator = $this->createMock( ConfigurationValidator::class );
		$taskHandlerRegistry = $this->createMock( TaskTypeHandlerRegistry::class );
		$configurationLoader = new PageConfigurationLoader( $configurationValidator, $taskHandlerRegistry,
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES, $this->getMockTitleFactory( [], false ),
			$wikiPageConfigLoader, $taskTitle, null,
			new HashConfig(),
			new EmptyTopicRegistry()
		);
		$topics = $configurationLoader->loadTopics();
		$this->assertSame( [], $topics );
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
				'general-science' => [
					'group' => 'science-technology-and-math',
					'oresTopics' => [ 'physics', 'biology' ],
				],
				'food-and-drink' => [
					'group' => 'history-and-society',
					'oresTopics' => [ 'food' ],
				],
			],
			'groups' => [ 'culture', 'science-technology-and-math', 'history-and-society' ],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			$config['topics']['*'] = [ 'group' => 'test', 'oresTopics' => [ 'test' ] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['topics']['general-science']['group'] );
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
		$configurationValidator = new ConfigurationValidator(
			$this->getMockMessageLocalizer( $customMessages ),
			$this->getMockCollationFactory(),
			$this->getMockTitleParser()
		);
		$wikiPageConfigLoader = $this->getMockWikiPageConfigLoader( [
			'8:TaskConfigPage' => $taskConfig,
			'8:TopicConfigPage' => $topicConfig,
		] );
		$taskTypeHandlerRegistry = $this->getMockTaskTypeHandlerRegistry( $configurationValidator );
		return new PageConfigurationLoader( $configurationValidator, $taskTypeHandlerRegistry,
			$topicType, $titleFactory, $wikiPageConfigLoader, $taskConfigTitle, $topicConfigTitle,
			new HashConfig(),
			new EmptyTopicRegistry()
		);
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
				return $customMessages[$key] ?? $this->getMockMessage( $key, ...$params );
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
			return $customMessages[$key] ?? $this->getMockMessage( $key );
		} );
		return $context;
	}

	/**
	 * @return CollationFactory
	 */
	protected function getMockCollationFactory() {
		$collation = $this->createNoOpMock( Collation::class, [ 'getSortKey' ] );
		$collation->method( 'getSortKey' )->willReturnArgument( 0 );
		$factory = $this->createMock( CollationFactory::class );
		$factory->method( 'getCategoryCollation' )->willReturn( $collation );
		return $factory;
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

	private function assertArrayKeyMatchesTaskTypeId( array $taskTypes ) {
		foreach ( $taskTypes as $taskTypeId => $taskType ) {
			$this->assertSame( $taskTypeId, $taskType->getId() );
		}
	}

}
