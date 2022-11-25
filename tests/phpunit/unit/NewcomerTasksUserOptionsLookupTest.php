<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use HashConfig;
use MediaWiki\User\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentity;
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
				SuggestedEdits::TOPICS_PREF => '[ "topics" ]',
				SuggestedEdits::TOPICS_ORES_PREF => '[ "ores" ]',
			],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '123',
				SuggestedEdits::TOPICS_PREF => '()%=',
				SuggestedEdits::TOPICS_ORES_PREF => 'true',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$experimentUserManager = $this->createPartialMock( ExperimentUserManager::class,
			[ 'isUserInVariant' ] );
		$experimentUserManager->method( 'isUserInVariant' )
			->with( $this->anything(), 'imagerecommendation' )
			->willReturn( false );

		$lookup = new NewcomerTasksUserOptionsLookup( $experimentUserManager, $userOptionsLookup,
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

		$config->set( 'GENewcomerTasksTopicType', PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'topics' ], $lookup->getTopics( $user1 ) );

		$config = new HashConfig( [
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GELinkRecommendationsFrontendEnabled' => true,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::areLinkRecommendationsEnabled
	 * @covers ::filterTaskTypes
	 */
	public function testLinkRecommendationAbTest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$user3 = new UserIdentityValue( 3, 'User3' );
		$user4 = new UserIdentityValue( 4, 'User4' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "copyedit", "links" ]'
			],
			'User2' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "links", "link-recommendation" ]',
			],
			'User3' => [],
			'User4' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "links", "link-recommendation" ]',
			],
		] );
		$config = new HashConfig( [
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$experimentUserManager = $this->createPartialMock( ExperimentUserManager::class,
			[ 'isUserInVariant' ] );
		$experimentUserManager->method( 'isUserInVariant' )
			->with( $this->anything(), 'imagerecommendation' )
			->willReturnCallback( static function ( UserIdentity $user, string $variant ) {
				return [
					'User1' => false,
					'User2' => false,
					'User3' => true,
					'User4' => true,
				][$user->getName()];
			} );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'links' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'links' ], $lookup->getTaskTypeFilter( $user4 ) );

		$config->set( 'GELinkRecommendationsFrontendEnabled', true );
		$config->set( 'GENewcomerTasksImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'link-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'link-recommendation' ], $lookup->getTaskTypeFilter( $user4 ) );
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
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$experimentUserManager = $this->createPartialMock( ExperimentUserManager::class,
			[ 'isUserInVariant' ] );
		$experimentUserManager->method( 'isUserInVariant' )
			->with( $this->anything(), 'imagerecommendation' )
			->willReturnCallback( static function ( UserIdentity $user, string $variant ) {
				return [
						   'User1' => false,
						   'User2' => false,
						   'User3' => true,
						   'User4' => true,
					   ][$user->getName()];
			} );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user4 ) );

		$config->set( 'GENewcomerTasksImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader()
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user4 ) );
	}

	/**
	 * @covers ::getDefaultTaskTypes
	 */
	public function testGetDefaultTaskTypes() {
		$experimentUserManager = $this->createMock( ExperimentUserManager::class );
		$experimentUserManager->method( 'isUserInVariant' )->willReturn( false );
		$user1 = new UserIdentityValue( 1, 'User1' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => []
		] );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( [
				'copyedit' => new TaskType( 'copyedit', 'easy' )
			]
		);
		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $configurationLoader
		);
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
			'GENewcomerTasksTopicType' => PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
		] );
		$experimentUserManager = $this->createConfiguredMock( ExperimentUserManager::class, [
			'isUserInVariant' => false,
		] );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$experimentUserManager, $userOptionsLookup, $config, $this->getConfigurationLoader( [ 'copyedit' ] )
		);
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * @param string[]|null $taskTypes
	 * @return ConfigurationLoader
	 */
	private function getConfigurationLoader( array $taskTypes = null ) {
		$taskTypes = $taskTypes ?? [ 'copyedit', 'links', 'link-recommendation', 'image-recommendation' ];
		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn(
			array_combine( $taskTypes, array_map( static function ( $taskTypeId ) {
				return new TaskType( $taskTypeId, 'easy' );
			}, $taskTypes ) )
		);
		return $configurationLoader;
	}

}
