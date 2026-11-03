<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater
 * @group Database
 */
class GrowthExperimentsUserImpactUpdaterTest extends MediaWikiIntegrationTestCase {

	public static function provideUserIsInCohort(): iterable {
		yield 'regular user' => [ [], true ];
		yield 'bot' => [ [ 'bot' ], false ];
	}

	/**
	 * @dataProvider provideUserIsInCohort
	 */
	public function testUserIsInCohort( array $groups, bool $expected ): void {
		$user = $this->getMutableTestUser( $groups )->getUser();
		$this->editPage( 'GrowthExperimentsUserImpactUpdaterTest', 'Edit by ' . $user->getName(), '', NS_MAIN, $user );

		/** @var GrowthExperimentsUserImpactUpdater $userImpactUpdater */
		$userImpactUpdater = $this->getServiceContainer()->getService( 'GrowthExperimentsUserImpactUpdater' );
		$this->assertSame( $expected, $userImpactUpdater->userIsInCohort( $user ) );
	}
}
