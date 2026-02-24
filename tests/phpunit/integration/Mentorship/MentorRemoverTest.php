<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\ReassignMenteesJob;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\Mentorship\MentorRemover
 * @covers \GrowthExperiments\Mentorship\ReassignMenteesFactory
 * @covers \GrowthExperiments\Mentorship\ReassignMentees
 * @covers \GrowthExperiments\Mentorship\ReassignMenteesJob
 */
class MentorRemoverTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMainCache( CACHE_NONE );
	}

	private function getNewMentor(): UserIdentity {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();

		$mentor = $this->getMutableTestUser()->getUserIdentity();
		$mentorWriter->addMentor( $mentorProvider->newMentorFromUserIdentity( $mentor ), $mentor, '' );
		$this->assertTrue( $mentorProvider->isMentor( $mentor ) );
		return $mentor;
	}

	private function getMenteeForMentor( UserIdentity $mentor ) {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();

		$mentee = $this->getMutableTestUser()->getUserIdentity();
		$mentorStore->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		$this->assertTrue( $mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		return $mentee;
	}

	public function testNoMentees() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorRemover = $geServices->getMentorRemover();
		$mentorProvider = $geServices->getMentorProvider();

		$mentor = $this->getNewMentor();

		$status = $mentorRemover->removeMentor( $mentor, $mentor, '', RequestContext::getMain() );
		$this->assertStatusOK( $status );
		$this->assertFalse( $mentorProvider->isMentor( $mentor ) );
	}

	public function testWithMenteesOneMentor() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorRemover = $geServices->getMentorRemover();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorStore = $geServices->getMentorStore();

		$mentor = $this->getNewMentor();
		$mentee = $this->getMenteeForMentor( $mentor );

		$status = $mentorRemover->removeMentor( $mentor, $mentor, '', RequestContext::getMain() );
		$this->assertStatusOK( $status );
		$this->assertFalse( $mentorProvider->isMentor( $mentor ) );

		$this->runJobs( runOptions: [ 'type' => ReassignMenteesJob::JOB_NAME ] );
		$this->assertFalse( $mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertNull( $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
	}

	public function testWithMenteesTwoMentors() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorRemover = $geServices->getMentorRemover();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorStore = $geServices->getMentorStore();

		$mentor = $this->getNewMentor();
		$otherMentor = $this->getNewMentor();
		$mentee = $this->getMenteeForMentor( $mentor );

		$status = $mentorRemover->removeMentor( $mentor, $mentor, '', RequestContext::getMain() );
		$this->assertStatusOK( $status );
		$this->assertFalse( $mentorProvider->isMentor( $mentor ) );

		$this->runJobs( runOptions: [ 'type' => ReassignMenteesJob::JOB_NAME ] );
		$this->assertFalse( $mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertTrue( $otherMentor->equals(
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )
		) );
	}

	public function testWithMenteesOverLimit() {
		$this->overrideConfigValue( 'GEMentorshipReassignMenteesBatchSize', 1 );

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorRemover = $geServices->getMentorRemover();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorStore = $geServices->getMentorStore();

		$mentor = $this->getNewMentor();
		// FIXME: an alternate mentor should not be necessary, but it is for now
		$otherMentor = $this->getNewMentor();

		$mentee = $this->getMenteeForMentor( $mentor );
		$menteeTwo = $this->getMenteeForMentor( $mentor );

		$status = $mentorRemover->removeMentor( $mentor, $mentor, '', RequestContext::getMain() );
		$this->assertStatusOK( $status );
		$this->assertFalse( $mentorProvider->isMentor( $mentor ) );

		$this->runJobs(
			// NOTE: This is the key assert in this test (to verify the job continuation logic)
			[ 'numJobs' => 2 ],
			[ 'type' => ReassignMenteesJob::JOB_NAME ]
		);
		$this->assertFalse( $mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertTrue( $otherMentor->equals(
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )
		) );
		$this->assertTrue( $otherMentor->equals(
			$mentorStore->loadMentorUser( $menteeTwo, MentorStore::ROLE_PRIMARY )
		) );
	}

	public function testWithHiddenMentees() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorRemover = $geServices->getMentorRemover();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorStore = $geServices->getMentorStore();

		$mentor = $this->getNewMentor();
		$normalMentee = $this->getMenteeForMentor( $mentor );
		$hiddenMentee = $this->getMenteeForMentor( $mentor );
		$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$hiddenMentee,
			new UltimateAuthority( $mentor ),
			'infinity',
			'',
			[ 'isHideUser' => true ]
		)->placeBlock();

		// needed to reverse MentorHooks::onBlockIpComplete()
		$mentorStore->setMentorForUser( $hiddenMentee, $mentor, MentorStore::ROLE_PRIMARY );

		$status = $mentorRemover->removeMentor( $mentor, $mentor, '', RequestContext::getMain() );
		$this->assertStatusOK( $status );
		$this->assertFalse( $mentorProvider->isMentor( $mentor ) );

		$this->runJobs(
			[ 'numJobs' => 1 ],
			// maxJobs is here to avoid an indefinite loop in a test context
			// runJobs asserts it finishes with "we have no more jobs to execute" (as opposed to
			// eg. "job execution limit was hit"). to meet that assertion, maxJobs needs to be
			// numJobs + 1.
			[ 'type' => ReassignMenteesJob::JOB_NAME, 'maxJobs' => 2 ]
		);
		$this->assertFalse( $mentorStore->hasAnyMentees( $mentor, MentorStore::ROLE_PRIMARY ) );
		$this->assertNull( $mentorStore->loadMentorUser( $normalMentee, MentorStore::ROLE_PRIMARY ) );
		$this->assertNull( $mentorStore->loadMentorUser( $hiddenMentee, MentorStore::ROLE_PRIMARY ) );
	}
}
