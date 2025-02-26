<?php

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\Config\Providers\SuggestedEditsConfigProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\CommunityConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use LogicException;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ConfigurationLoader\CommunityConfigurationLoader
 */
class CommunityConfigurationLoaderTest extends MediaWikiUnitTestCase {
	/**
	 * Helper method to create a new CommunityConfigurationLoader instance
	 *
	 * @param array $overrides dependencies to override
	 * @return CommunityConfigurationLoader
	 */
	private function newLoader( array $overrides = [] ) {
		// Specifically pass null so that null value is preserved
		$suggestedEditsConfigProvider = array_key_exists( 'suggestedEditsConfigProvider', $overrides )
			? $overrides['suggestedEditsConfigProvider']
			: $this->createStub( SuggestedEditsConfigProvider::class );
		$topicConfigData = array_key_exists( 'topicConfigData', $overrides )
			? $overrides['topicConfigData']
			: null;

		return new CommunityConfigurationLoader(
			$overrides['configurationValidator'] ?? $this->createStub(
				ConfigurationValidator::class ),
			$overrides['taskTypeHandlerRegistry'] ?? $this->createStub(
				TaskTypeHandlerRegistry::class ),
				$overrides['topicsRegistry'] ?? $this->createStub(
					ITopicRegistry::class ),
			$overrides['topicType'] ?? 'ores',
			$suggestedEditsConfigProvider,
			$overrides['titleFactory'] ?? $this->createStub( TitleFactory::class ),
			$topicConfigData,
			$overrides['logger'] ?? $this->createStub( LoggerInterface::class ) );
	}

	public function testLoadTaskTypesConfigNullProvider() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'debug' )
			->with(
				$this->stringContains( 'Suggested Edits config provider is null' ),
				$this->anything()
			);

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => null,
			'logger' => $logger
		] );

		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$result = $wrappedLoader->loadTaskTypesConfig();

		$this->assertSame( [], $result );
	}

	public function testLoadTaskTypesConfigSuccess() {
		$sampleConfig = [
			'copyedit' => [
				'icon' => 'articleCheck',
				'group' => 'easy',
				'templates' => [ 'Template1', 'Template2' ]
			]
		];

		$status = StatusValue::newGood( $sampleConfig );

		$configProvider = $this->createMock( SuggestedEditsConfigProvider::class );
		$configProvider->expects( $this->once() )
			->method( 'loadForNewcomerTasks' )
			->willReturn( $status );

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => $configProvider
		] );

		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$result = $wrappedLoader->loadTaskTypesConfig();

		$this->assertSame( $sampleConfig, $result );
	}

	public function testLoadTaskTypesConfigError() {
		$errorStatus = StatusValue::newFatal( 'some-error' );

		$configProvider = $this->createMock( SuggestedEditsConfigProvider::class );
		$configProvider->expects( $this->once() )
			->method( 'loadForNewcomerTasks' )
			->willReturn( $errorStatus );

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => $configProvider
		] );

		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$result = $wrappedLoader->loadTaskTypesConfig();

		$this->assertSame( $errorStatus, $result );
	}

	public function testLoadInfoboxTemplatesNullProvider() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'debug' )
			->with(
				$this->stringContains( 'Suggested Edits config provider is null' ),
				$this->anything()
			);

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => null,
			'logger' => $logger
		] );

		$result = $loader->loadInfoboxTemplates();
		$this->assertSame( [], $result );
	}

	public function testLoadInfoboxTemplatesSuccess() {
		$infoboxTemplates = [ 'Infobox1', 'Infobox2' ];
		$configValue = (object)[ 'GEInfoboxTemplates' => $infoboxTemplates ];

		$status = StatusValue::newGood( $configValue );

		$configProvider = $this->createMock( SuggestedEditsConfigProvider::class );
		$configProvider->expects( $this->once() )
			->method( 'loadValidConfiguration' )
			->willReturn( $status );

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => $configProvider
		] );

		$result = $loader->loadInfoboxTemplates();

		$this->assertSame( $infoboxTemplates, $result );
	}

	public function testLoadInfoboxTemplatesError() {
		$errorStatus = StatusValue::newFatal( 'some-error' );

		$configProvider = $this->createMock( SuggestedEditsConfigProvider::class );
		$configProvider->expects( $this->once() )
			->method( 'loadValidConfiguration' )
			->willReturn( $errorStatus );

		$loader = $this->newLoader( [
			'suggestedEditsConfigProvider' => $configProvider
		] );

		$result = $loader->loadInfoboxTemplates();

		$this->assertSame( $errorStatus, $result );
	}

	public function testLoadTopicsConfig() {
		$topicConfigData = [
			'topics' => [
				'art' => [
					'group' => 'culture',
					'oresTopics' => [ 'music', 'painting' ],
				],
				'science' => [
					'group' => 'stem',
					'oresTopics' => [ 'physics', 'biology' ],
				]
			],
			'groups' => [ 'culture', 'stem' ]
		];

		$loader = $this->newLoader( [
			'topicConfigData' => $topicConfigData
		] );

		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$result = $wrappedLoader->loadTopicsConfig();

		$this->assertSame( $topicConfigData, $result );
	}

	public function testLoadTopicsWithNullConfig() {
		$loader = $this->newLoader();
		$result = $loader->loadTopics();

		$this->assertSame( [], $result );
	}

	public function testLoadTopicsSuccess() {
		$topicConfigData = [
			'topics' => [
				'art' => [
					'group' => 'culture',
					'oresTopics' => [ 'music', 'painting' ],
				],
			],
			'groups' => [ 'culture' ]
		];

		$mockTopic = $this->createMock( OresBasedTopic::class );
		$mockTopic->method( 'getId' )->willReturn( 'art' );

		$loader = $this->newLoader( [
			'topicConfigData' => $topicConfigData
		] );

		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$wrappedLoader->topics = [ $mockTopic ];

		$result = $loader->loadTopics();

		$this->assertCount( 1, $result );
		$this->assertSame( $mockTopic, $result[0] );
	}

	public function testDisableAndEnableTaskTypeState() {
		$loader = $this->newLoader();
		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );

		$this->assertSame( [], $wrappedLoader->disabledTaskTypeIds, 'disabledTaskTypeIds should start empty' );
		$this->assertSame( [], $wrappedLoader->enabledTaskTypeIds, 'enabledTaskTypeIds should start empty' );

		$loader->disableTaskType( 'copyedit' );
		$this->assertSame( [ 'copyedit' ], $wrappedLoader->disabledTaskTypeIds,
			'disabledTaskTypeIds should contain the disabled task ID' );

		$loader->disableTaskType( 'references' );
		$this->assertSame( [ 'copyedit', 'references' ], $wrappedLoader->disabledTaskTypeIds,
			'disabledTaskTypeIds should contain both disabled task IDs' );

		// Disabling the same task twice doesn't duplicate it
		$loader->disableTaskType( 'copyedit' );
		$this->assertSame( [ 'copyedit', 'references' ], $wrappedLoader->disabledTaskTypeIds,
			'disabledTaskTypeIds should not contain duplicates' );

		$loader->enableTaskType( 'copyedit' );
		$this->assertSame( [ 'copyedit' ], $wrappedLoader->enabledTaskTypeIds,
			'enabledTaskTypeIds should contain the enabled task ID' );

		$loader->enableTaskType( 'update' );
		$this->assertContains( 'copyedit', $wrappedLoader->enabledTaskTypeIds,
			'enabledTaskTypeIds should contain first enabled task ID' );
		$this->assertContains( 'update', $wrappedLoader->enabledTaskTypeIds,
			'enabledTaskTypeIds should contain second enabled task ID' );
	}

	public function testDisableTaskTypeAfterLoadingThrowsException() {
		$loader = $this->newLoader();
		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );

		$wrappedLoader->taskTypes = [ $this->createMock( TaskType::class ) ];

		// Attempting to disable a task type should throw LogicException
		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'must be called before task types are loaded' );

		$loader->disableTaskType( 'copyedit' );
	}

	public function testEnableTaskTypeAfterLoadingThrowsException() {
		$loader = $this->newLoader();
		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );
		$wrappedLoader->taskTypes = [ $this->createMock( TaskType::class ) ];

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'must be called before task types are loaded' );

		$loader->enableTaskType( 'copyedit' );
	}
}
