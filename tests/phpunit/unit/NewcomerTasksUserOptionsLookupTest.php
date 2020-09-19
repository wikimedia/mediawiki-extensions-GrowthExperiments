<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use HashConfig;
use MediaWiki\User\StaticUserOptionsLookup;
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
		$user1 = new UserIdentityValue( 1, 'User1', 1 );
		$user2 = new UserIdentityValue( 2, 'User2', 2 );
		$user3 = new UserIdentityValue( 3, 'User3', 3 );
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
		] );

		$lookup = new NewcomerTasksUserOptionsLookup( $userOptionsLookup, $config );
		$this->assertSame( [ 'tasktypes' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'ores' ], $lookup->getTopicFilter( $user1 ) );
		$this->assertSame( SuggestedEdits::DEFAULT_TASK_TYPES, $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [], $lookup->getTopicFilter( $user2 ) );
		$this->assertSame( SuggestedEdits::DEFAULT_TASK_TYPES, $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [], $lookup->getTopicFilter( $user3 ) );

		$config->set( 'GENewcomerTasksTopicType', PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$lookup = new NewcomerTasksUserOptionsLookup( $userOptionsLookup, $config );
		$this->assertSame( [ 'topics' ], $lookup->getTopicFilter( $user1 ) );
	}

}
