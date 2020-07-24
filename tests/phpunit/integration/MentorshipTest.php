<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RequestContext;

/**
 * @group medium
 * @group Database
 */
class MentorshipTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\HomepageModules\Mentorship::render
	 */
	public function testRenderNoMentorsAvailable() {
		$this->insertPage( 'MentorsList', 'no user links here' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$context = new DerivativeContext( RequestContext::getMain() );
		$mentorshipModule = new Mentorship(
			$context,
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsExperimentUserManager' )
		);

		$this->assertEmpty( $mentorshipModule->render( HomepageModule::RENDER_DESKTOP ) );
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
		$mentorshipModule = new Mentorship(
			$context,
			MediaWikiServices::getInstance()->get( 'GrowthExperimentsExperimentUserManager' )
		);
		$context->getOutput()->enableOOUI();
		$this->assertEmpty( $mentorshipModule->render( HomepageModule::RENDER_DESKTOP ) );
		$mentee->setOption( Mentor::MENTOR_PREF, $mentor->getId() );
		$mentee->saveSettings();
		$this->assertNotEmpty( $mentorshipModule->render( HomepageModule::RENDER_DESKTOP ) );
	}
}
