<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit\NewcomerTasks\Task;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory
 */
class TaskSetFiltersFactoryTest extends MediaWikiUnitTestCase {

	private const TWELVE_INTERESTS = [
		'Interest 1', 'Interest 2', 'Interest 3', 'Interest 4', 'Interest 5', 'Interest 6',
		'Interest 7', 'Interest 8', 'Interest 9', 'Interest 10', 'Interest 11', 'Interest 12',
	];

	public function testNewFromUserUsesThePreferences(): void {
		$factory = new TaskSetFiltersFactory(
			$this->getUserOptionsLookup(),
			$this->getFeatureManager( false ),
			new ServiceOptions(
				TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GENewcomerTasksMaxInterestsForQueries' => 10,
				] ),
			),
		);

		$this->assertEquals(
			new TaskSetFilters( [ 'copyedit' ], [ 'ores' ], SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $this->getUser() )
		);
	}

	public function testNewFromUserWithExplicitTaskTypes(): void {
		$userOptionsLookup = $this->getUserOptionsLookup();
		$userOptionsLookup->expects( $this->never() )->method( 'getTaskTypeFilter' );
		$factory = new TaskSetFiltersFactory(
			$userOptionsLookup,
			$this->getFeatureManager( false ),
			new ServiceOptions(
				TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GENewcomerTasksMaxInterestsForQueries' => 10,
				] ),
			),
		);

		$this->assertEquals(
			new TaskSetFilters( [ 'links' ], [ 'ores' ], SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $this->getUser(), [ 'links' ] )
		);
	}

	public function testNewFromUserIgnoresTheInterestsOfAControlUser(): void {
		$factory = new TaskSetFiltersFactory(
			$this->getUserOptionsLookup( self::TWELVE_INTERESTS ),
			$this->getFeatureManager( false ),
			new ServiceOptions(
				TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GENewcomerTasksMaxInterestsForQueries' => 10,
				] ),
			),
		);

		$this->assertEquals(
			new TaskSetFilters( [ 'copyedit' ], [ 'ores' ], SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $this->getUser() )
		);
	}

	public function testNewFromUserFiltersATreatmentUserByInterests(): void {
		$userOptionsLookup = $this->getUserOptionsLookup( self::TWELVE_INTERESTS );
		$userOptionsLookup->expects( $this->never() )->method( 'getTopics' );
		$userOptionsLookup->expects( $this->once() )->method( 'getTopicsMatchMode' );
		$maxInterests = 9;
		$factory = new TaskSetFiltersFactory(
			$userOptionsLookup,
			$this->getFeatureManager( true ),
			new ServiceOptions(
				TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GENewcomerTasksMaxInterestsForQueries' => $maxInterests,
				] ),
			),
		);

		$this->assertEquals(
			new TaskSetFilters(
				[ 'copyedit' ],
				[],
				SearchStrategy::TOPIC_MATCH_MODE_AND,
				array_slice( self::TWELVE_INTERESTS, 0, $maxInterests )
			),
			$factory->newFromUser( $this->getUser() )
		);
	}

	public function testNewFromUserGivesATreatmentUserWithoutInterestsNoFilters(): void {
		$factory = new TaskSetFiltersFactory(
			$this->getUserOptionsLookup(),
			$this->getFeatureManager( true ),
			new ServiceOptions(
				TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'GENewcomerTasksMaxInterestsForQueries' => 10,
				] ),
			),
		);

		$this->assertEquals(
			new TaskSetFilters( [ 'copyedit' ], topicFiltersMode: SearchStrategy::TOPIC_MATCH_MODE_AND ),
			$factory->newFromUser( $this->getUser() )
		);
	}

	public function testGetInterestFilters(): void {
		$user = $this->getUser();
		$maxInterests = 9;
		$options = new ServiceOptions(
			TaskSetFiltersFactory::CONSTRUCTOR_OPTIONS,
			new HashConfig( [
				'GENewcomerTasksMaxInterestsForQueries' => $maxInterests,
			] ),
		);
		$controlFactory = new TaskSetFiltersFactory(
			$this->getUserOptionsLookup( self::TWELVE_INTERESTS ),
			$this->getFeatureManager( false ),
			$options,
		);
		$treatmentFactory = new TaskSetFiltersFactory(
			$this->getUserOptionsLookup( self::TWELVE_INTERESTS ),
			$this->getFeatureManager( true ),
			$options,
		);

		$this->assertSame( [], $controlFactory->getInterestFilters( $user ) );
		$this->assertSame(
			array_slice( self::TWELVE_INTERESTS, 0, $maxInterests ),
			$treatmentFactory->getInterestFilters( $user )
		);
	}

	private function getUser(): UserIdentityValue {
		return new UserIdentityValue( 1, 'User1' );
	}

	/**
	 * @param string[] $interests
	 */
	private function getUserOptionsLookup(
		array $interests = []
	): NewcomerTasksUserOptionsLookup&MockObject {
		$userOptionsLookup = $this->createMock( NewcomerTasksUserOptionsLookup::class );
		$userOptionsLookup->method( 'getTaskTypeFilter' )->willReturn( [ 'copyedit' ] );
		$userOptionsLookup->method( 'getTopics' )->willReturn( [ 'ores' ] );
		$userOptionsLookup->method( 'getTopicsMatchMode' )
			->willReturn( SearchStrategy::TOPIC_MATCH_MODE_AND );
		$userOptionsLookup->method( 'getInterests' )->willReturn( $interests );
		return $userOptionsLookup;
	}

	private function getFeatureManager( bool $isTreatment ): FeatureManager {
		$featureManager = $this->createMock( FeatureManager::class );
		$featureManager->method( 'isEarlyOnboardingExperimentTreatment' )->willReturn( $isTreatment );
		return $featureManager;
	}

}
