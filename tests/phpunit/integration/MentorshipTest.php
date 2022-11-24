<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GlobalVarConfig;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\Mentorship;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use RequestContext;

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
		$this->insertPage( 'MentorsList', 'no user links here' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
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
		$mentor = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
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
		$this->assertNotEmpty( $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers ::render
	 * @covers ::getIntroText
	 */
	public function testGetIntroText() {
		$mentor = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]|description' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
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
		$this->assertStringContainsString( '"description"', $result );
	}
}
