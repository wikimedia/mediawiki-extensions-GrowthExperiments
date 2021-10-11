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
 */
class MentorshipTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\HomepageModules\Mentorship::render
	 */
	public function testRenderNoMentorsAvailable() {
		$this->insertPage( 'MentorsList', 'no user links here' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$context = new DerivativeContext( RequestContext::getMain() );
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager()
		);

		$this->assertEmpty( $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/**
	 * @covers \GrowthExperiments\HomepageModules\Mentorship::render
	 */
	public function testRenderSuccess() {
		$mentor = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
		$mentorshipModule = new Mentorship(
			$context,
			GlobalVarConfig::newInstance(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getMentorManager()
		);
		$context->getOutput()->enableOOUI();
		$this->assertNotEmpty( $mentorshipModule->render( IDashboardModule::RENDER_DESKTOP ) );
	}
}
