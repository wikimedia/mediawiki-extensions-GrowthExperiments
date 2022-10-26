<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Mentor;
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

	/** @inheritDoc */
	protected $tablesUsed = [ 'user', 'growthexperiments_mentor_mentee' ];

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
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		$writer = $geServices->getMentorWriter();
		$quittingMentorUser = $this->getMutableTestUser()->getUser();
		$mentorAuto = new Mentor(
			$this->getMutableTestUser()->getUserIdentity(),
			null,
			'foo',
			true,
			2
		);
		$mentorManual = new Mentor(
			$this->getMutableTestUser()->getUserIdentity(),
			null,
			'foo',
			false,
			2
		);
		$writer->addMentor( $mentorAuto, $mentorAuto->getUserIdentity(), '' );
		$writer->addMentor( $mentorManual, $mentorManual->getUserIdentity(), '' );

		$quittingMentees = $this->getNMenteesForMentor(
			3, $quittingMentorUser, $mentorAuto->getUserIdentity()
		);
		$manualMentees = $this->getNMenteesForMentor(
			3, $mentorManual->getUserIdentity(), $quittingMentorUser
		);

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $quittingMentorUser );
		$reassignMentees = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getReassignMenteesFactory()
			->newReassignMentees( $quittingMentorUser, $quittingMentorUser, $context );

		$this->assertTrue( $reassignMentees->doReassignMentees( 'foo' ) );

		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();
		$this->assertArrayEquals(
			$quittingMentees,
			$mentorStore->getMenteesByMentor( $mentorAuto->getUserIdentity(), MentorStore::ROLE_PRIMARY )
		);
		$this->assertArrayEquals(
			$manualMentees,
			$mentorStore->getMenteesByMentor( $mentorManual->getUserIdentity(), MentorStore::ROLE_PRIMARY )
		);
		$this->assertArrayEquals(
			$manualMentees,
			$mentorStore->getMenteesByMentor( $quittingMentorUser, MentorStore::ROLE_BACKUP )
		);
	}
}
