<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\UserImpact\UserImpact;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\UserEditTracker;

/**
 * @covers \GrowthExperiments\Api\ApiQueryNextSuggestedTaskType
 * @group Database
 */
class ApiQueryNextSuggestedTaskTypeTest extends ApiTestCase {

	public function testNotLoggedIn() {
		$configurationLoader = new StaticConfigurationLoader( [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
		] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$this->expectApiErrorCode( 'mustbeloggedin-generic' );
		$this->doApiRequest(
			[ 'action' => 'query', 'meta' => 'growthnextsuggestedtasktype', 'gnsttactivetasktype' => 'copyedit' ],
			null,
			null,
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);
	}

	public function testInvalidTaskTypeParameter() {
		$configurationLoader = new StaticConfigurationLoader( [
			new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY ),
		] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$this->expectApiErrorCode( 'badvalue' );
		$this->doApiRequestWithToken( [
			'action' => 'query',
			'meta' => 'growthnextsuggestedtasktype',
			'gnsttactivetasktype' => 'link-recommendation',
		], null, $this->getServiceContainer()->getUserFactory()->newAnonymous() );
	}

	public function testRequiredTaskTypeParameter() {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', true );
		$this->expectApiErrorCode( 'missingparam' );
		$this->doApiRequestWithToken(
			[ 'action' => 'query', 'meta' => 'growthnextsuggestedtasktype' ],
			null,
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);
	}

	public function testGetNextSuggestedTaskType() {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', true );
		$this->overrideConfigValue( 'GELevelingUpManagerTaskTypeCountThresholdMultiple', 5 );
		$userImpact = $this->createMock( UserImpact::class );
		$userImpact->expects( $this->exactly( 2 ) )
			->method( 'getEditCountByTaskType' )
			->willReturn( [
				'copyedit' => 4,
				'link-recommendation' => 0,
			] );
		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->expects( $this->exactly( 3 ) )
			->method( 'getUserEditCount' )
			->willReturn( 5 );
		$userImpactLookup = $this->createMock( UserImpactLookup::class );
		$userImpactLookup->expects( $this->exactly( 2 ) )
			->method( 'getUserImpact' )
			->willReturn( $userImpact );
		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSet = $this->createMock( TaskSet::class );
		$taskSet->method( 'count' )
			->willReturn( 1 );
		$taskSuggester->method( 'suggest' )
			->willReturn( $taskSet );
		$taskSuggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggesterFactory->method( 'create' )->willReturn( $taskSuggester );
		$taskType1 = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$taskType2 = new TaskType( 'link-recommendation', TaskType::DIFFICULTY_EASY );
		$configurationLoader = new StaticConfigurationLoader( [ $taskType1, $taskType2 ] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$this->setService( 'GrowthExperimentsUserImpactLookup_Computed', $userImpactLookup );
		$this->setService( 'GrowthExperimentsTaskSuggesterFactory', $taskSuggesterFactory );
		$this->setService( 'UserEditTracker', $userEditTracker );

		$result = $this->doApiRequestWithToken( [
			'action' => 'query',
			'meta' => 'growthnextsuggestedtasktype',
			'gnsttactivetasktype' => 'copyedit',
		] );
		$this->assertSame( 'link-recommendation', $result[0]['query']['growthnextsuggestedtasktype'] );
	}

	public function testExecuteWhenFeatureDisabled() {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Suggested edits' );
		// Omit gnsttactivetasktype so we pass param validation (param is optional when disabled)
		// and reach execute(), where the guard dies with "Suggested edits is disabled".
		$this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthnextsuggestedtasktype',
			],
			null,
			$this->getTestSysop()->getAuthority()
		);
	}

	/**
	 * getAllowedParams must not call ConfigurationLoader::getTaskTypes
	 * when Suggested Edits is disabled.
	 */
	public function testGetAllowedParamsWhenFeatureDisabled() {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );

		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->expects( $this->never() )
			->method( 'getTaskTypes' );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoaderMock );

		$result = $this->doApiRequest( [
			'action' => 'paraminfo',
			'modules' => 'query+growthnextsuggestedtasktype',
		] );

		$this->assertArrayHasKey( 'paraminfo', $result[0] );
		$this->assertArrayHasKey( 'modules', $result[0]['paraminfo'] );
	}
}
