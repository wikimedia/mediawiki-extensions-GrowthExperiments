<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Tests\Helpers\CreateMenteeHelpers;
use MediaWiki\Json\FormatJson;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\MentorManager
 * @group Database
 */
class MentorManagerTest extends MediaWikiIntegrationTestCase {
	use CreateMenteeHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	public function testRenderInvalidMentor() {
		$this->insertPage( 'MediaWiki:GrowthMentors.json', FormatJson::encode( [
			'Mentors' => [
				1234 => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
			],
		] ) );

		$this->assertNull( GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager()
			->getMentorForUserSafe( $this->getTestUser()->getUser() )
		);
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getUserIdentity
	 */
	public function testGetMentorUserNew() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$sysop = $this->getTestSysop()->getUser();
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor(
			$geServices->getMentorProvider()->newMentorFromUserIdentity( $sysop ),
			$sysop,
			''
		) );

		$mentorManager = $geServices->getMentorManager();
		$mentor = $mentorManager->getMentorForUserSafe( $this->getTestUser()->getUser() );
		$this->assertEquals( $sysop->getId(), $mentor->getUserIdentity()->getId() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getUserIdentity
	 */
	public function testGetMentorUserExisting() {
		$sysop = $this->getTestSysop()->getUser();
		$user = $this->getMutableTestUser()->getUser();
		GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore()
			->setMentorForUser( $user, $sysop, MentorStore::ROLE_PRIMARY );

		$mentor = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager()
			->getMentorForUserSafe( $user );
		$this->assertEquals( $sysop->getName(), $mentor->getUserIdentity()->getName() );
	}

	public function testMentorCannotBeMentee() {
		$user = $this->getMutableTestUser()->getUser();
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor(
			$geServices->getMentorProvider()->newMentorFromUserIdentity( $user ),
			$user,
			''
		) );

		$this->assertNull( $geServices->getMentorManager()->getMentorForUserSafe( $user ) );
	}

	public function testMentorCannotBeMenteeMoreMentors() {
		$userMentee = $this->getMutableTestUser()->getUser();
		$userMentor = $this->getTestSysop()->getUser();
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$provider = $geServices->getMentorProvider();
		$writer = $geServices->getMentorWriter();
		$this->assertStatusGood( $writer->addMentor(
			$provider->newMentorFromUserIdentity( $userMentee ),
			$userMentee,
			''
		) );
		$this->assertStatusGood( $writer->addMentor(
			$provider->newMentorFromUserIdentity( $userMentor ),
			$userMentor,
			''
		) );

		$mentor = $geServices->getMentorManager()->getMentorForUserSafe( $userMentee );
		$this->assertEquals( $mentor->getUserIdentity()->getName(), $userMentor->getName() );
	}

	public function testNoMentorAvailable() {
		$this->assertNull(
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getMentorManager()
				->getMentorForUserSafe( $this->getMutableTestUser()->getUser() )
		);
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 */
	public function testRenderMentorText() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentor = $geServices->getMentorProvider()->newMentorFromUserIdentity( $mentorUser );
		$mentor->setIntroText( 'This is a sample text.' );
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor( $mentor, $mentorUser, '' ) );

		$mentee = $this->getMutableTestUser()->getUser();
		$geServices->getMentorStore()
			->setMentorForUser(
				$mentee,
				$mentorUser,
				MentorStore::ROLE_PRIMARY
			);
		$mentorManager = $geServices->getMentorManager();

		$mentor = $mentorManager->getMentorForUserSafe( $mentee );
		$this->assertEquals( 'This is a sample text.', $mentor->getIntroText() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 */
	public function testRenderFallbackMentorText() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$geServices->getMentorStore()
			->setMentorForUser(
				$mentee,
				$mentorUser,
				MentorStore::ROLE_PRIMARY
			);
		$mentorManager = $geServices->getMentorManager();

		$mentor = $mentorManager->getMentorForUserSafe( $mentee );
		$this->assertStringContainsString(
			'This experienced user knows you\'re new and can help you with editing.',
			$mentor->getIntroText()
		);
	}

	public function testGetMentorshipStateForUser() {
		$mentor = $this->getTestSysop()->getUser();

		$enabledUser = $this->createMentee( $mentor, [
			'user_options' => [ MentorManager::MENTORSHIP_ENABLED_PREF => 1 ],
		] );

		$disabledUser = $this->createMentee( $mentor, [
			'user_options' => [ MentorManager::MENTORSHIP_ENABLED_PREF => 0 ],
		] );

		$optedOutUser = $this->createMentee( $mentor, [
			'user_options' => [ MentorManager::MENTORSHIP_ENABLED_PREF => 2 ],
		] );

		$mentorManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager();
		$this->assertEquals(
			IMentorManager::MENTORSHIP_ENABLED,
			$mentorManager->getMentorshipStateForUser( $enabledUser )
		);
		$this->assertFalse( $mentorManager->didUserExplicitlyOptIntoMentorship( $enabledUser ) );

		$this->assertEquals(
			IMentorManager::MENTORSHIP_DISABLED,
			$mentorManager->getMentorshipStateForUser( $disabledUser )
		);
		$this->assertFalse( $mentorManager->didUserExplicitlyOptIntoMentorship( $disabledUser ) );

		$this->assertEquals(
			IMentorManager::MENTORSHIP_OPTED_OUT,
			$mentorManager->getMentorshipStateForUser( $optedOutUser )
		);
		$this->assertFalse( $mentorManager->didUserExplicitlyOptIntoMentorship( $optedOutUser ) );
	}

	public function testGetMentorshipStateForUserBroken() {
		$mentor = $this->getTestSysop()->getUser();
		$brokenUser = $this->createMentee( $mentor, [
			'user_options' => [ MentorManager::MENTORSHIP_ENABLED_PREF => 123 ],
		] );

		$this->assertEquals(
			IMentorManager::MENTORSHIP_DISABLED,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getMentorManager()
				->getMentorshipStateForUser( $brokenUser )
		);
	}

	public function testMentorshipStateExplicitlyOpted() {
		$mentor = $this->getTestSysop()->getUser();
		$explicitlyOptedInUser = $this->createMentee( $mentor, [
			'user_options' => [ MentorManager::MENTORSHIP_ENABLED_PREF => 50 ],
		] );

		$mentorManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager();
		$this->assertEquals(
			MentorManager::MENTORSHIP_ENABLED,
			$mentorManager->getMentorshipStateForUser( $explicitlyOptedInUser )
		);
		$this->assertTrue( $mentorManager->didUserExplicitlyOptIntoMentorship( $explicitlyOptedInUser ) );
	}

	public function testGetMentorshipStatusForTemporaryAccount() {
		$mentorManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager();
		$tempAccount = $this->getServiceContainer()->getTempUserCreator()->create(
			null, new FauxRequest()
		)->getUser();

		$this->assertEquals(
			IMentorManager::MENTORSHIP_DISABLED,
			$mentorManager->getMentorshipStateForUser( $tempAccount )
		);
	}

	/**
	 * @see T351415
	 */
	public function testSetMentorshipStateForUserOptOut() {
		$mentorManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager();
		$menteeUser = $this->getMutableTestUser()->getUserIdentity();
		$mentorUser = $this->getMutableTestUser()->getUserIdentity();

		// prepare the mentor list
		$this->insertPage( 'MediaWiki:GrowthMentors.json', FormatJson::encode( [
			'Mentors' => [
				$mentorUser->getId() => [
					'message' => null,
					'weight' => 2,
					'automaticallyAssigned' => true,
				],
			],
		] ) );

		// assert default behaviour matches expectations
		$this->assertEquals(
			IMentorManager::MENTORSHIP_ENABLED,
			$mentorManager->getMentorshipStateForUser( $menteeUser )
		);
		$this->assertEquals(
			$mentorUser,
			$mentorManager->getMentorForUserSafe( $menteeUser )->getUserIdentity()
		);

		// opt out of mentorship; this should drop the mentor mentee relationship
		$mentorManager->setMentorshipStateForUser( $menteeUser, IMentorManager::MENTORSHIP_OPTED_OUT );

		// assert mentor mentee relationship is dropped
		$this->assertNull(
			$mentorManager->getMentorForUserSafe( $menteeUser )
		);
		$this->assertNull(
			$mentorManager->getMentorForUserIfExists( $menteeUser )
		);
	}

	public function testOptOutMentorshipDropsRelationship() {
		$mentee = $this->getTestUser()->getUser();
		$mentor = $this->getTestSysop()->getUser();

		$mentorManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorManager();
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		// Must be in this order; otherwise, setMentorshipStateForUser() would negate the
		// setMentorForUser()
		$mentorManager->setMentorshipStateForUser( $mentee, IMentorManager::MENTORSHIP_OPTED_OUT );
		$mentorStore->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );

		// getMentorForUserSafe() should claim there is no mentor...
		// NOTE: While this is an assert, it is not the main point of the test. This line mainly
		// prepares the right conditions for the final assert at the next line.
		$this->assertNull( $mentorManager->getMentorForUserSafe( $mentee, MentorStore::ROLE_PRIMARY ) );

		// ...and there should be no in the database either (this is the key assert).
		$this->assertNull( $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
	}

	public function testBlockDropsRelationship() {
		$mentee = $this->getTestUser()->getUser();
		$mentor = $this->getTestSysop()->getUser();

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
		$mentorManager = $geServices->getMentorManager();
		$mentorStore = $geServices->getMentorStore();

		// Must be in this order; otherwise, blocking the mentee would drop the relationship
		$blockUserFactory->newBlockUser( $mentee, $mentor, 'indefinite' )->placeBlockUnsafe();
		$mentorStore->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );

		// getMentorForUserSafe() should claim there is no mentor...
		// NOTE: While this is an assert, it is not the main point of the test. This line mainly
		// prepares the right conditions for the final assert at the next line.
		$this->assertNull( $mentorManager->getMentorForUserSafe( $mentee, MentorStore::ROLE_PRIMARY ) );

		// ...and there should be no in the database either (this is the key assert).
		$this->assertNull( $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
	}

	public function testBackupMentorIsValid() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();
		$mentorManager = $geServices->getMentorManager();

		$mentee = $this->getMutableTestUser()->getUser();
		$otherUser = $this->getMutableTestUser()->getUser();
		$mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $otherUser ), $otherUser,
			'Test'
		);

		$mentorStore->setMentorForUser( $mentee, $otherUser, MentorStore::ROLE_BACKUP );
		$this->assertTrue( $otherUser->equals(
			$mentorManager->getMentorForUserSafe( $mentee, MentorStore::ROLE_BACKUP )->getUserIdentity()
		) );

		$mentorWriter->removeMentor(
			$mentorProvider->newMentorFromUserIdentity( $otherUser ), $otherUser,
			'Test'
		);
		$this->assertNull( $mentorManager->getMentorForUserSafe( $mentee, MentorStore::ROLE_BACKUP ) );
	}

	public function testAutoAssignedPrimaryUserIsNotAway(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorManager = $geServices->getMentorManager();

		$mentee = $this->getMutableTestUser()->getUser();
		$mentorUser = $this->getNewMentorUser();
		$mentorStatusManager = $geServices->getMentorStatusManager();
		$mentorStatusManager->markMentorAsAway( $mentorUser, 1 );

		$this->assertNull( $mentorManager->getRandomAutoAssignedMentor( $mentee ) );
	}

	public function testGetEffectiveMentorForUserSafe_ActivePrimaryAssigned(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorManager = $geServices->getMentorManager();

		$mentorUser = $this->getNewMentorUser();
		$mentee = $this->getMutableTestUser()->getUser();

		$actualMentor = $mentorManager->getEffectiveMentorForUserSafe(
			$mentee
		);

		$this->assertTrue( $actualMentor->getUserIdentity()->equals( $mentorUser ) );
	}

	public function testGetEffectiveMentorForUserSafe_AwayPrimaryNotAssigned(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorManager = $geServices->getMentorManager();
		$mentorStatusManager = $geServices->getMentorStatusManager();

		$mentorUser = $this->getNewMentorUser();
		$mentorStatusManager->markMentorAsAway( $mentorUser, 1 );

		$mentee = $this->getMutableTestUser()->getUser();
		$actualMentor = $mentorManager->getEffectiveMentorForUserSafe(
			$mentee
		);

		$this->assertNull( $actualMentor );
	}

	public function testGetEffectiveMentorForUserSafe_ActiveBackupAssigned(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorManager = $geServices->getMentorManager();
		$mentorStatusManager = $geServices->getMentorStatusManager();

		$mentee = $this->getMutableTestUser()->getUser();
		$mentorUser = $this->getNewMentorUserForMentee( $mentee );
		$mentorStatusManager->markMentorAsAway( $mentorUser, 1 );

		$potentialBackupMentorUser = $this->getNewMentorUser();

		$actualMentor = $mentorManager->getEffectiveMentorForUserSafe(
			$mentee
		);

		$this->assertTrue( $actualMentor->getUserIdentity()->equals( $potentialBackupMentorUser ) );
	}

	public function testGetEffectiveMentorForUserSafe_AwayBackupNotAssigned(): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorManager = $geServices->getMentorManager();
		$mentorStatusManager = $geServices->getMentorStatusManager();

		$mentee = $this->getMutableTestUser()->getUser();
		$mentorUser = $this->getNewMentorUserForMentee( $mentee );
		$mentorStatusManager->markMentorAsAway( $mentorUser, 1 );

		$potentialBackupMentorUser = $this->getNewMentorUser();
		$mentorStatusManager->markMentorAsAway( $potentialBackupMentorUser, 1 );

		$actualMentor = $mentorManager->getEffectiveMentorForUserSafe(
			$mentee
		);

		$this->assertNull( $actualMentor );
	}

	private function getNewMentorUserForMentee( UserIdentity $mentee ): User {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();
		$mentorUser = $this->getNewMentorUser();
		$mentorStore->setMentorForUser( $mentee, $mentorUser, MentorStore::ROLE_PRIMARY );

		return $mentorUser;
	}

	private function getNewMentorUser(): User {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorWriter = $geServices->getMentorWriter();
		$mentorProvider = $geServices->getMentorProvider();
		$newGroup = 'new group' . mt_rand();
		$mentorUser = $this->getTestUser( [ $newGroup ] )->getUser();
		$mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentorUser ), $mentorUser,
			'Test Primary'
		);

		return $mentorUser;
	}
}
