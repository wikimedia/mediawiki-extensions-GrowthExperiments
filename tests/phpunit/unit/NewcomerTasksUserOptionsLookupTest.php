<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentTestKitchenManager;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\StaticExperimentManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup
 */
class NewcomerTasksUserOptionsLookupTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getTopics
	 * @covers ::getTaskTypeFilter
	 * @covers ::getJsonListOption
	 */
	public function testSuggest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$user3 = new UserIdentityValue( 3, 'User3' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit" ]',
				SuggestedEdits::TOPICS_ORES_PREF => '[ "ores" ]',
			],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '123',
				SuggestedEdits::TOPICS_ORES_PREF => 'true',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$featureManager = $this->getFeatureManager();

		$lookup = new NewcomerTasksUserOptionsLookup( $featureManager, $userOptionsLookup,
			$config, $this->getConfigurationLoader( [ 'copyedit', 'links' ] ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'ores' ], $lookup->getTopics( $user1 ) );
		$this->assertSame( SearchStrategy::TOPIC_MATCH_MODE_OR, $lookup->getTopicsMatchMode( $user1 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [], $lookup->getTopics( $user2 ) );
		$this->assertSame( SearchStrategy::TOPIC_MATCH_MODE_OR, $lookup->getTopicsMatchMode( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [], $lookup->getTopics( $user3 ) );
		$this->assertSame( SearchStrategy::TOPIC_MATCH_MODE_OR, $lookup->getTopicsMatchMode( $user3 ) );

		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GELinkRecommendationsFrontendEnabled' => true,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::areImageRecommendationsEnabled
	 * @covers ::filterTaskTypes
	 */
	public function testImageRecommendationAbTest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$user3 = new UserIdentityValue( 3, 'User3' );
		$user4 = new UserIdentityValue( 4, 'User4' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "image-recommendation" ]',
			],
			'User3' => [],
			'User4' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "image-recommendation" ]',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$featureManager = $this->getFeatureManager();

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user4 ) );

		$config->set( 'GENewcomerTasksImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user4 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::areSectionImageRecommendationsEnabled
	 * @covers ::filterTaskTypes
	 */
	public function testSectionImageRecommendationAbTest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$sectionImageTaskType = SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "' . $sectionImageTaskType . '" ]',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$featureManager = $this->getFeatureManager();

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );

		$config->set( 'GENewcomerTasksSectionImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'section-image-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::areReviseToneRecommendationsEnabled
	 * @covers ::filterTaskTypes
	 */
	public function testReviseToneRecommendationAbTest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$reviseToneTaskType = ReviseToneTaskTypeHandler::TASK_TYPE_ID;
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "' . $reviseToneTaskType . '" ]',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$featureManager = $this->getFeatureManager();

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );

		$featureManager = $this->getFeatureManager(
			ExperimentTestKitchenManager::REVISE_TONE_EXPERIMENT_TREATMENT_GROUP_NAME
		);

		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'revise-tone' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @covers ::getDefaultTaskTypes
	 */
	public function testGetDefaultTaskTypes() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$featureManager = $this->getFeatureManager();
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$sectionImageTaskType = SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID;
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( [
			'copyedit' => new TaskType( 'copyedit', 'easy' ),
			$sectionImageTaskType => new TaskType( $sectionImageTaskType, 'easy' ),
		] );
		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $configurationLoader
		);
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );

		$config->set( 'GENewcomerTasksSectionImageRecommendationsEnabled', true );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::getDefaultTaskTypes
	 */
	public function testCommunityConfiguration() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "links" ]',
			],
			'User2' => [],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$featureManager = $this->getFeatureManager();
		$lookup = new NewcomerTasksUserOptionsLookup(
			$featureManager, $userOptionsLookup, $config, $this->getConfigurationLoader( [ 'copyedit' ] )
		);
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @param string[]|null $taskTypes
	 * @return ConfigurationLoader
	 */
	private function getConfigurationLoader( ?array $taskTypes = null ) {
		$taskTypes ??= [
			'copyedit', 'links',
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
			ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
			SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
			ReviseToneTaskTypeHandler::TASK_TYPE_ID,
		];
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn(
			array_combine( $taskTypes, array_map( static function ( $taskTypeId ) {
				return new TaskType( $taskTypeId, 'easy' );
			}, $taskTypes ) )
		);
		return $configurationLoader;
	}

	/**
	 * Provide a configured FeatureManager with all relevant config feature flags enabled
	 *
	 * @param string|null $defaultVariant
	 * @return FeatureManager
	 */
	private function getFeatureManager( ?string $defaultVariant = 'control' ): FeatureManager {
		$extensionRegistryMock = $this->createMock( ExtensionRegistry::class );
		$extensionRegistryMock->method( 'isLoaded' )->willReturn( true );

		$config = new HashConfig( [
			'GEReviseToneSuggestedEditEnabled' => true,
			'GEHomepageSuggestedEditsEnabled' => true,
		] );
		$sut = new FeatureManager( $extensionRegistryMock, $config );
		$sut->setExperimentManager( new StaticExperimentManager( new ServiceOptions( [ 'GEHomepageDefaultVariant' ], [
			'GEHomepageDefaultVariant' => $defaultVariant,
		] ) ) );
		return $sut;
	}

}
