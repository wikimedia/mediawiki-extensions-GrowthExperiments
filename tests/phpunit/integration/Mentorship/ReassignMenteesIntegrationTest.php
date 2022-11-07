<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\ReassignMentees
 * @group Database
 * @group medium
 */
class ReassignMenteesIntegrationTest extends MediaWikiIntegrationTestCase {

	private function getNMenteesForMentor(
		int $numOfMentees,
		UserIdentity $primaryMentor,
		UserIdentity $backupMentor
	): array {
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		$mentees = [];
		for ( $i = 0; $i < $numOfMentees; $i++ ) {
			$mentee = $this->getMutableTestUser()->getUserIdentity();
			$mentorStore->setMentorForUser( $mentee, $primaryMentor, MentorStore::ROLE_PRIMARY );
			$mentorStore->setMentorForUser( $mentee, $backupMentor, MentorStore::ROLE_BACKUP );

			$mentees[] = $mentee;
		}
		return $mentees;
	}

	/**
	 * @covers ::doReassignMentees
	 */
	public function testWithBackupMentors() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$this->setMwGlobals( 'wgGEHomepageManualAssignmentMentorsList', 'MentorsList/Manual' );

		$quittingMentor = $this->getMutableTestUser()->getUser();
		$mentorAuto = $this->getMutableTestUser()->getUserIdentity();
		$mentorManual = $this->getMutableTestUser()->getUserIdentity();
		$this->insertPage( 'MentorsList', "[[User:{$mentorAuto->getName()}]]" );
		$this->insertPage( 'MentorsList/Manual', "[[User:{$mentorManual->getName()}]]" );

		$quittingMentees = $this->getNMenteesForMentor( 3, $quittingMentor, $mentorAuto );
		$manualMentees = $this->getNMenteesForMentor( 3, $mentorManual, $quittingMentor );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $quittingMentor );
		$reassignMentees = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getReassignMenteesFactory()
			->newReassignMentees( $quittingMentor, $quittingMentor, $context );

		$this->assertTrue( $reassignMentees->doReassignMentees( 'foo' ) );

		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();
		$this->assertArrayEquals(
			$quittingMentees,
			$mentorStore->getMenteesByMentor( $mentorAuto, MentorStore::ROLE_PRIMARY )
		);
		$this->assertArrayEquals(
			$manualMentees,
			$mentorStore->getMenteesByMentor( $mentorManual, MentorStore::ROLE_PRIMARY )
		);
	}
}
