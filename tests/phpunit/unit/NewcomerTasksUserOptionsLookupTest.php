<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\InterestValidator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup
 */
class NewcomerTasksUserOptionsLookupTest extends MediaWikiUnitTestCase {

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

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup,
			$config,
			$this->getConfigurationLoader( [ 'copyedit', 'links' ] ),
			$this->createNoOpMock( InterestValidator::class ),
		);
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
			$userOptionsLookup, $config, $this->getConfigurationLoader(),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit', 'link-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

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

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup, $config, $this->getConfigurationLoader(),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user4 ) );

		$config->set( 'GENewcomerTasksImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup, $config, $this->getConfigurationLoader(),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user3 ) );
		$this->assertSame( [ 'copyedit', 'image-recommendation' ], $lookup->getTaskTypeFilter( $user4 ) );
	}

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

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup, $config, $this->getConfigurationLoader(),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );

		$config->set( 'GENewcomerTasksSectionImageRecommendationsEnabled', true );

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup, $config, $this->getConfigurationLoader(),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit', 'links' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit', 'section-image-recommendation' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	public function testGetDefaultTaskTypes() {
		$user1 = new UserIdentityValue( 1, 'User1' );
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
			$userOptionsLookup, $config, $configurationLoader,
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );

		$config->set( 'GENewcomerTasksSectionImageRecommendationsEnabled', true );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
	}

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
		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup, $config, $this->getConfigurationLoader( [ 'copyedit' ] ),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user1 ) );
		$this->assertSame( [ 'copyedit' ], $lookup->getTaskTypeFilter( $user2 ) );
	}

	/**
	 * A conversion-map fallback target may not be configured on the wiki. When Revise Tone is
	 * disabled, "revise-tone" converts to "copyedit"; if "copyedit" is not a configured task
	 * type, convertTaskTypes() must not emit it (it would later fatal on a null dereference in
	 * LevelingUpManager::getTaskTypesGroupedByDifficulty()). The link-recommendation => links
	 * fallback behaves the same way. Regression test for T431668.
	 */
	public function testConvertTaskTypesFiltersNonExistentFallback() {
		$user = new UserIdentityValue( 1, 'User1' );
		$config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => false,
			'GELinkRecommendationsFrontendEnabled' => false,
			'GENewcomerTasksImageRecommendationsEnabled' => false,
			'GENewcomerTasksSectionImageRecommendationsEnabled' => false,
			'GEReviseToneSuggestedEditEnabled' => false,
		] );
		$reviseTone = ReviseToneTaskTypeHandler::TASK_TYPE_ID;
		$linkRecommendation = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;

		// Neither "copyedit" nor "links" is configured here, so both fallbacks resolve to a task
		// type that does not exist: they must be dropped, not returned.
		$lookupWithout = new NewcomerTasksUserOptionsLookup(
			new StaticUserOptionsLookup( [] ), $config,
			$this->getConfigurationLoader( [ $reviseTone, $linkRecommendation ] ),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$lookupWithoutWrapper = TestingAccessWrapper::newFromObject( $lookupWithout );
		$mapWithout = $lookupWithoutWrapper->getConversionMap( $user );
		$this->assertFalse( $mapWithout[$reviseTone] );
		$this->assertFalse( $mapWithout[$linkRecommendation] );
		$this->assertSame(
			[], $lookupWithout->convertTaskTypes( [ $reviseTone, $linkRecommendation ], $user )
		);

		// When "copyedit" and "links" are configured, the fallbacks resolve and are returned.
		$lookupWith = new NewcomerTasksUserOptionsLookup(
			new StaticUserOptionsLookup( [] ), $config,
			$this->getConfigurationLoader( [ 'copyedit', 'links', $reviseTone, $linkRecommendation ] ),
			$this->createNoOpMock( InterestValidator::class ),
		);
		$lookupWithWrapper = TestingAccessWrapper::newFromObject( $lookupWith );
		$mapWith = $lookupWithWrapper->getConversionMap( $user );
		$this->assertSame( 'copyedit', $mapWith[$reviseTone] );
		$this->assertSame( 'links', $mapWith[$linkRecommendation] );
		$this->assertSame(
			[ 'copyedit', 'links' ],
			$lookupWith->convertTaskTypes( [ $reviseTone, $linkRecommendation ], $user )
		);
	}

	public function testGetValidatedInterests() {
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [ AccountSetupHooks::INTEREST_ARTICLES_PROP => '[ "Coffee", "Tea" ]' ],
			'User2' => [ AccountSetupHooks::INTEREST_ARTICLES_PROP => '[ 1 ]' ],
		] );

		$invalidInterests = [ 'Tea' ];
		$interestValidator = $this->createMock( InterestValidator::class );
		$interestValidator->method( 'validate' )->willReturnCallback(
			static fn ( $interests ) => [
				'valid' => array_values( array_diff( $interests, $invalidInterests ) ),
				'invalid' => array_values( array_intersect( $interests, $invalidInterests ) ),
			],
		);

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup,
			new HashConfig( [] ),
			$this->getConfigurationLoader(),
			$interestValidator,
		);

		$this->assertSame(
			[ 'Coffee' ],
			$lookup->getValidatedInterests( new UserIdentityValue( 1, 'User1' ) )
		);
		$this->assertSame( [], $lookup->getValidatedInterests( new UserIdentityValue( 2, 'User2' ) ) );
		$this->assertSame( [], $lookup->getValidatedInterests( new UserIdentityValue( 3, 'User3' ) ) );
	}

	public function testGetValidatedInterestsValidatesEachUserOnce() {
		$userOptionsLookup = new StaticUserOptionsLookup( [
			'User1' => [ AccountSetupHooks::INTEREST_ARTICLES_PROP => '[ "Coffee" ]' ],
			'User2' => [ AccountSetupHooks::INTEREST_ARTICLES_PROP => '[ "Tea" ]' ],
		] );

		$interestValidator = $this->createMock( InterestValidator::class );
		$interestValidator->expects( $this->exactly( 2 ) )->method( 'validate' )->willReturnCallback(
			static fn ( $interests ) => [ 'valid' => $interests, 'invalid' => [] ],
		);

		$lookup = new NewcomerTasksUserOptionsLookup(
			$userOptionsLookup,
			new HashConfig( [] ),
			$this->getConfigurationLoader(),
			$interestValidator,
		);

		$user1 = new UserIdentityValue( 1, 'User1' );
		$user2 = new UserIdentityValue( 2, 'User2' );
		$this->assertSame( [ 'Coffee' ], $lookup->getValidatedInterests( $user1 ) );
		$this->assertSame( [ 'Coffee' ], $lookup->getValidatedInterests( $user1 ) );
		$this->assertSame( [ 'Tea' ], $lookup->getValidatedInterests( $user2 ) );
		$this->assertSame( [ 'Tea' ], $lookup->getValidatedInterests( $user2 ) );
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

}
