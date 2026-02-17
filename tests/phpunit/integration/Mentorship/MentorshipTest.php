<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;

/**
 * @group medium
 * @group Database
 * @coversDefaultClass \GrowthExperiments\HomepageModules\Mentorship
 */
class MentorshipTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::render
	 */
	public function testRenderNoMentorsAvailable() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache(),
			$services->getUserEditTracker()
		);

		$this->assertSame( '', $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers ::render
	 */
	public function testRenderSuccess() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$this->assertStatusGood( $growthServices->getMentorWriter()->addMentor(
			$growthServices->getMentorProvider()->newMentorFromUserIdentity( $mentorUser ),
			$mentorUser,
			''
		) );

		$mentee = $this->getMutableTestUser()->getUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );

		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache(),
			$services->getUserEditTracker()
		);
		$context->getOutput()->enableOOUI();
		$this->assertNotEmpty( $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers ::render
	 * @covers ::getIntroText
	 */
	public function testGetIntroText() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentor = $growthServices->getMentorProvider()->newMentorFromUserIdentity(
			$this->getTestUser( 'sysop' )->getUser()
		);
		$mentor->setIntroText( 'description' );
		$this->assertStatusGood( $growthServices->getMentorWriter()->addMentor(
			$mentor,
			$mentor->getUserIdentity(),
			''
		) );

		$mentee = $this->getMutableTestUser()->getUser();
		$growthServices->getMentorStore()->setMentorForUser(
			$mentee,
			$mentor->getUserIdentity(),
			MentorStore::ROLE_PRIMARY
		);

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
			$services->getGenderCache(),
			$services->getUserEditTracker()
		);
		$context->getOutput()->enableOOUI();

		$result = $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'description', $result );
	}
}
