<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore
 */
class StarredMenteesStoreTest extends MediaWikiIntegrationTestCase {

	public function testGetStarredMenteesSkipsHiddenUsers() {
		$store = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getStarredMenteesStore();
		$mentor = $this->getMutableTestUser()->getUser();
		$visibleMentee = $this->getMutableTestUser()->getUser();
		$hiddenMentee = $this->getMutableTestUser()->getUser();
		$store->starMentee( $mentor, $visibleMentee );
		$store->starMentee( $mentor, $hiddenMentee );
		$this->assertStatusGood( $this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$hiddenMentee, new UltimateAuthority( $this->getTestSysop()->getUser() ), 'infinity', '',
			[ 'isHideUser' => true ]
		)->placeBlock() );

		$names = array_map(
			static fn ( UserIdentity $user ) => $user->getName(),
			$store->getStarredMentees( $mentor )
		);

		$this->assertSame( [ $visibleMentee->getName() ], $names );
	}
}
