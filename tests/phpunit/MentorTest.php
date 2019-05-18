<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use GrowthExperiments\Mentor;
use MediaWikiTestCase;
use RequestContext;

/**
 * @group Database
 */
class MentorTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 * @dataProvider provideInvalidMentorsList
	 * @param string $mentorsListConfig
	 * @expectedException \Exception
	 * @expectedExceptionMessageRegExp /wgGEHomepageMentorsList is invalid/
	 */
	public function testNewFromMenteeInvalidMentorList( $mentorsListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorsListConfig );

		Mentor::newFromMentee( $this->getTestUser()->getUser(), true );
	}

	public static function provideInvalidMentorsList() {
		return [
			[ null ],
			[ '' ],
			[ '  ' ],
			[ ':::' ],
			[ 'NonExistentPage' ],
		];
	}

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 * @expectedException \Exception
	 * @expectedExceptionMessageRegExp /Invalid mentor/
	 */
	public function testRenderInvalidMentor() {
		$this->insertPage( 'MentorsList', '[[User:InvalidUser]]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		Mentor::newFromMentee( $this->getTestUser()->getUser(), true );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::getMentorUser
	 */
	public function testGetMentorUserNew() {
		$sysop = $this->getTestSysop()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $sysop->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->assertFalse( Mentor::newFromMentee( $this->getTestUser()->getUser() ) );

		$mentor = Mentor::newFromMentee( $this->getTestUser()->getUser(), true );
		$this->assertEquals( $sysop->getId(), $mentor->getMentorUser()->getId() );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 * @covers \GrowthExperiments\Mentor::getMentorUser
	 */
	public function testGetMentorUserExisting() {
		$sysop = $this->getTestSysop()->getUser();
		$user = $this->getMutableTestUser()->getUser();
		$user->setOption( Mentor::MENTOR_PREF, $sysop->getId() );
		$user->saveSettings();

		$mentor = Mentor::newFromMentee( $user );
		$this->assertEquals( $sysop->getName(), $mentor->getMentorUser()->getName() );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 * @expectedException \Exception
	 * @expectedExceptionMessageRegExp /Homepage Mentorship module: no mentor available for/
	 */
	public function testMentorCannotBeMentee() {
		$user = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $user->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		Mentor::newFromMentee( $user, true );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 */
	public function testMentorCannotBeMenteeMoreMentors() {
		$userMentee = $this->getMutableTestUser()->getUser();
		$userMentor = $this->getTestSysop()->getUser();

		$this->insertPage(
			'MentorsList',
			'[[User:' .
			$userMentee->getName() .
			']][[User:' .
			$userMentor->getName() .
			']]'
		);
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentor = Mentor::newFromMentee( $userMentee, true );
		$this->assertEquals( $mentor->getMentorUser()->getName(), $userMentor->getName() );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::newFromMentee
	 * @expectedException \Exception
	 * @expectedExceptionMessageRegExp /Homepage Mentorship module: no mentor available for/
	 */
	public function testNoMentorAvailable() {
		$this->insertPage( 'MentorsList', 'Mentors' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		Mentor::newFromMentee( $this->getMutableTestUser()->getUser(), true );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::getIntroText
	 */
	public function testRenderCustomMentorText() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList',
			'[[User:' . $mentorUser->getName() . ']] | This is a sample text.' );
		$mentee->setOption( Mentor::MENTOR_PREF, $mentorUser->getId() );
		$mentee->saveSettings();

		$mentor = Mentor::newFromMentee( $mentee );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$this->assertContains( 'This is a sample text.', $mentor->getIntroText( $context ) );
	}

	/**
	 * @covers \GrowthExperiments\Mentor::getIntroText
	 */
	public function testRenderFallbackMentorText() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentorUser->getName() . ']]' );
		$mentee->setOption( Mentor::MENTOR_PREF, $mentorUser->getId() );
		$mentee->saveSettings();

		$mentor = Mentor::newFromMentee( $mentee );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$this->assertContains(
			'This experienced user knows you\'re new and can help you with editing.',
			$mentor->getIntroText( $context )
		);
	}
}
