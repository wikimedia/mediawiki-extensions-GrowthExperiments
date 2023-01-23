<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GlobalVarConfig;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @group medium
 * @group Database
 * @coversDefaultClass \GrowthExperiments\HomepageModules\Mentorship
 */
class MentorshipTest extends MediaWikiIntegrationTestCase {

	/** @inheritDoc */
	protected $tablesUsed = [ 'user', 'page' ];

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		$this->insertPage( 'MediaWiki:GrowthMentors.json', '{"Mentors": []}' );
	}

	/**
	 * @covers ::render
	 */
	public function testRenderNoMentorsAvailable() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache()
		);

		$this->assertSame( '', $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers ::render
	 */
	public function testRenderSuccess() {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$growthServices->getMentorWriter()->addMentor(
			$growthServices->getMentorProvider()->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		);

		$mentee = $this->getMutableTestUser()->getUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );

		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache()
		);
		$context->getOutput()->enableOOUI();
		$this->assertNotEmpty( $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers ::render
	 * @covers ::getIntroText
	 */
	public function testGetIntroText() {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentor = $growthServices->getMentorProvider()->newMentorFromUserIdentity(
			$this->getTestUser( 'sysop' )->getUser()
		);
		$mentor->setIntroText( 'description' );
		$growthServices->getMentorWriter()->addMentor(
			$mentor,
			$mentor->getUserIdentity(),
			''
		);

		$mentee = $this->getMutableTestUser()->getUser();
		$growthServices->getMentorStore()->setMentorForUser(
			$mentee,
			$mentor->getUserIdentity(),
			MentorStore::ROLE_PRIMARY
		);

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache()
		);
		$context->getOutput()->enableOOUI();

		$result = $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'description', $result );
	}
}
