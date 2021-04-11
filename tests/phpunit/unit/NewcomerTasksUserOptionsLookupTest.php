<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\VariantHooks;
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
	 * @covers ::getTopicFilter
	 * @covers ::getTaskTypeFilter
	 * @covers ::getJsonListOption
	 */
	public function testSuggest() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$user3 = new UserIdentityValue( 3, 'User3' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [
				SuggestedEdits::TASKTYPES_PREF => '[ "tasktypes" ]',
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
		] );
		$experimentUserManager = $this->createPartialMock( ExperimentUserManager::class,
			[ 'isUserInVariant' ] );
		$experimentUserManager->method( 'isUserInVariant' )
			->with( $this->anything(), VariantHooks::VARIANT_LINK_RECOMMENDATION_ENABLED )
			->willReturn( false );

		$lookup = new NewcomerTasksUserOptionsLookup( $experimentUserManager, $userOptionsLookup, $config );
		$this->assertSame( [ 'tasktypes' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'ores' ], $lookup->getTopicFilter( $user1 ) );
		$this->assertSame( SuggestedEdits::DEFAULT_TASK_TYPES, $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [], $lookup->getTopicFilter( $user2 ) );
		$this->assertSame( SuggestedEdits::DEFAULT_TASK_TYPES, $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [], $lookup->getTopicFilter( $user3 ) );

		$config->set( 'GENewcomerTasksTopicType', PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$lookup = new NewcomerTasksUserOptionsLookup( $experimentUserManager, $userOptionsLookup, $config );
		$this->assertSame( [ 'topics' ], $lookup->getTopicFilter( $user1 ) );
	}

	/**
	 * @covers ::getTaskTypeFilter
	 * @covers ::areLinkRecommendationsEnabled
	 * @covers ::filterTaskTypes
	 */
	public function testT278123() {
		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$user3 = new UserIdentityValue( 3, 'User3' );
		$user4 = new UserIdentityValue( 4, 'User4' );
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [],
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
		] );
		$experimentUserManager = $this->createPartialMock( ExperimentUserManager::class,
			[ 'isUserInVariant' ] );
		$experimentUserManager->method( 'isUserInVariant' )
			->with( $this->anything(), VariantHooks::VARIANT_LINK_RECOMMENDATION_ENABLED )
			->willReturnCallback( function ( UserIdentity $user, string $variant ) {
				return [
					'User1' => false,
					'User2' => false,
					'User3' => true,
					'User4' => true,
				][$user->getName()];
			} );

		$lookup = new NewcomerTasksUserOptionsLookup( $experimentUserManager, $userOptionsLookup, $config );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'links' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'links' ], $lookup->getTaskTypeFilter( $user4 ) );

		$config->set( 'GELinkRecommendationsFrontendEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup( $experimentUserManager, $userOptionsLookup, $config );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'links' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'link-recommendation' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'link-recommendation' ], $lookup->getTaskTypeFilter( $user4 ) );
	}

}
