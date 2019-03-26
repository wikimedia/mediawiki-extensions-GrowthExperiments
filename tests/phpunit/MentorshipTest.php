<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\HomepageModules\Mentorship;
use MediaWikiTestCase;
use RequestContext;

/**
 * Class MentorshipTest
 *
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
		$mentorshipModule = new Mentorship( $context );

		$this->assertEmpty( $mentorshipModule->render() );
	}

	/**
	 * @covers \GrowthExperiments\HomepageModules\Mentorship::render
	 */
	public function testRenderSuccess() {
		$mentor = $this->getTestUser( 'sysop' )->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$context = new DerivativeContext( RequestContext::getMain() );
		$mentorshipModule = new Mentorship( $context );
		$context->getOutput()->enableOOUI();

		$this->assertNotEmpty( $mentorshipModule->render() );
	}

}
