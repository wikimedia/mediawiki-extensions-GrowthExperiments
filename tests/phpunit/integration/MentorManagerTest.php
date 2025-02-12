<?php

namespace GrowthExperiments\Tests\Integration;

use Exception;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\MentorManager
 * @group Database
 */
class MentorManagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		// for some reason, the content of MediaWiki:GrowthMentors.json survives between tests,
		// causing failures. Reset the structured mentor list before every test.
		$this->insertPage( 'MediaWiki:GrowthMentors.json', FormatJson::encode( [
			'Mentors' => [],
		] ) );

		// Prevent caching of MediaWiki:GrowthMentors.json
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

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No mentor available' );

		$this->getMentorManager()->getMentorForUser( $this->getTestUser()->getUser() );
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

		$mentorManager = $this->getMentorManager();
		$mentor = $mentorManager->getMentorForUser( $this->getTestUser()->getUser() );
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

		$mentor = $this->getMentorManager()->getMentorForUser( $user );
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

		$this->expectException( WikiConfigException::class );
		$this->assertNull( $this->getMentorManager()->getMentorForUser( $user ) );
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

		$mentor = $this->getMentorManager()->getMentorForUser( $userMentee );
		$this->assertEquals( $mentor->getUserIdentity()->getName(), $userMentor->getName() );
	}

	public function testNoMentorAvailable() {
		$this->expectException( WikiConfigException::class );
		$this->getMentorManager()->getMentorForUser( $this->getMutableTestUser()->getUser() );
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
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$mentorManager = $this->getMentorManager( $context );

		$mentor = $mentorManager->getMentorForUser( $mentee );
		$this->assertEquals( 'This is a sample text.', $mentor->getIntroText() );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 */
	public function testRenderFallbackMentorText() {
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore()
			->setMentorForUser(
				$mentee,
				$mentorUser,
				MentorStore::ROLE_PRIMARY
			);
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$mentorManager = $this->getMentorManager( $context );

		$mentor = $mentorManager->getMentorForUser( $mentee );
		$this->assertStringContainsString(
			'This experienced user knows you\'re new and can help you with editing.',
			$mentor->getIntroText()
		);
	}

	public function testGetMentorshipStateForUser() {
		$optionManager = $this->getServiceContainer()->getUserOptionsManager();

		$enabledUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$enabledUser,
			MentorManager::MENTORSHIP_ENABLED_PREF,
			1
		);
		$optionManager->saveOptions( $enabledUser );

		$disabledUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$disabledUser,
			MentorManager::MENTORSHIP_ENABLED_PREF,
			0
		);
		$optionManager->saveOptions( $disabledUser );

		$optedOutUser = $this->getMutableTestUser()->getUser();
		$optionManager->setOption(
			$optedOutUser,
			MentorManager::MENTORSHIP_ENABLED_PREF,
			2
		);
		$optionManager->saveOptions( $optedOutUser );

		$mentorManager = $this->getMentorManager();
		$this->assertEquals(
			IMentorManager::MENTORSHIP_ENABLED,
			$mentorManager->getMentorshipStateForUser( $enabledUser )
		);
		$this->assertEquals(
			IMentorManager::MENTORSHIP_DISABLED,
			$mentorManager->getMentorshipStateForUser( $disabledUser )
		);
		$this->assertEquals(
			IMentorManager::MENTORSHIP_OPTED_OUT,
			$mentorManager->getMentorshipStateForUser( $optedOutUser )
		);
	}

	public function testGetMentorshipStateForUserBroken() {
		$optionManager = $this->getServiceContainer()->getUserOptionsManager();
		$brokenUser = $this->getMutableTestUser()->getUser();

		$optionManager->setOption(
			$brokenUser,
			MentorManager::MENTORSHIP_ENABLED_PREF,
			123
		);
		$optionManager->saveOptions( $brokenUser );

		$this->assertEquals(
			IMentorManager::MENTORSHIP_DISABLED,
			$this->getMentorManager()->getMentorshipStateForUser( $brokenUser )
		);
	}

	/**
	 * @see T351415
	 */
	public function testSetMentorshipStateForUserOptOut() {
		$mentorManager = $this->getMentorManager();
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

		$mentorManager = $this->getMentorManager();
		$mentorStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStore();

		// Must be in this order; otherwise, setMentorshipStateForUser() would negate the
		// setMentorForUser()
		$mentorManager->setMentorshipStateForUser( $mentee, IMentorManager::MENTORSHIP_OPTED_OUT );
		$mentorStore->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );

		// getMentorForUserSafe() should claim there is no mentor...
		$this->assertNull( $mentorManager->getMentorForUserSafe( $mentee, MentorStore::ROLE_PRIMARY ) );

		// ...and there should be no in the database either (this is the key assert).
		$this->assertNull( $mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
	}

	public function testBackupMentorIsValid() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter = $geServices->getMentorWriter();
		$mentorManager = $this->getMentorManager();

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

	/**
	 * @param IContextSource|null $context
	 * @return MentorManager
	 */
	private function getMentorManager( ?IContextSource $context = null ) {
		$coreServices = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $coreServices );
		$context ??= RequestContext::getMain();

		return new MentorManager(
			$growthServices->getMentorStore(),
			$growthServices->getMentorStatusManager(),
			$growthServices->getMentorProvider(),
			$coreServices->getUserIdentityLookup(),
			$coreServices->getUserOptionsLookup(),
			$coreServices->getUserOptionsManager(),
			$context->getRequest()->wasPosted()
		);
	}
}
