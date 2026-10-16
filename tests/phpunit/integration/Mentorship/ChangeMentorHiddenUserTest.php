<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Logging\LogPage;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\ChangeMentor
 */
class ChangeMentorHiddenUserTest extends MediaWikiIntegrationTestCase {

	private function addMentor( User $user ): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor(
			$geServices->getMentorProvider()->newMentorFromUserIdentity( $user ),
			$user,
			''
		) );
	}

	private function hideUser( User $user ): void {
		$this->assertStatusGood( $this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$user, new UltimateAuthority( $this->getTestSysop()->getUser() ), 'infinity', '', [ 'isHideUser' => true ]
		)->placeBlock() );
	}

	private function getGrowthLogDeleted(): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'log_deleted' )
			->from( 'logging' )
			->where( [ 'log_type' => 'growthexperiments' ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	public function testHiddenMenteeIsRejected() {
		$mentor = $this->getMutableTestUser()->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->addMentor( $mentor );
		$this->hideUser( $mentee );

		$status = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getChangeMentorFactory()
			->newChangeMentor( $mentee, $mentor )
			->execute( $mentor, '' );

		$this->assertStatusError( 'growthexperiments-homepage-claimmentee-no-user', $status );
		$this->assertSame( [], $this->getGrowthLogDeleted() );
	}

	public function testHiddenPreviousMentorIsSuppressedInLog() {
		$hiddenMentor = $this->getMutableTestUser()->getUser();
		$newMentor = $this->getMutableTestUser()->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->addMentor( $newMentor );
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$geServices->getMentorStore()->setMentorForUser( $mentee, $hiddenMentor, MentorStore::ROLE_PRIMARY );
		$this->hideUser( $hiddenMentor );

		$status = $geServices->getChangeMentorFactory()
			->newChangeMentor( $mentee, $newMentor )
			->execute( $newMentor, "Admin removed {$hiddenMentor->getName()} from mentorship" );

		$this->assertStatusGood( $status );
		$this->assertSame(
			[ (string)( LogPage::SUPPRESSED_ACTION | LogPage::DELETED_COMMENT ) ],
			$this->getGrowthLogDeleted()
		);
	}

	public function testHiddenNewMentorIsRejected() {
		$hiddenMentor = $this->getMutableTestUser()->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->addMentor( $hiddenMentor );
		$this->hideUser( $hiddenMentor );

		$status = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getChangeMentorFactory()
			->newChangeMentor( $mentee, $this->getTestSysop()->getUser() )
			->execute( $hiddenMentor, '' );

		$this->assertStatusError( 'noname', $status );
		$this->assertSame( [], $this->getGrowthLogDeleted() );
	}
}
