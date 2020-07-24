<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use Exception;
use GrowthExperiments\Mentorship\Mentor;
use MediaWikiTestCase;
use RequestContext;

/**
 * @group Database
 */
class MentorTest extends MediaWikiTestCase {

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
	 * @dataProvider provideInvalidMentorsList
	 * @param string $mentorsListConfig
	 */
	public function testNewFromMenteeInvalidMentorList( $mentorsListConfig ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', $mentorsListConfig );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'wgGEHomepageMentorsList is invalid' );

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
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
	 */
	public function testRenderInvalidMentor() {
		$this->insertPage( 'MentorsList', '[[User:InvalidUser]]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'no mentor available' );

		Mentor::newFromMentee( $this->getTestUser()->getUser(), true );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getMentorUser
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
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
	 * @covers \GrowthExperiments\Mentorship\Mentor::getMentorUser
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
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
	 */
	public function testMentorCannotBeMentee() {
		$user = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $user->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Homepage Mentorship module: no mentor available for' );

		Mentor::newFromMentee( $user, true );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
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
	 * @covers \GrowthExperiments\Mentorship\Mentor::newFromMentee
	 */
	public function testNoMentorAvailable() {
		$this->insertPage( 'MentorsList', 'Mentors' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Homepage Mentorship module: no mentor available for' );

		Mentor::newFromMentee( $this->getMutableTestUser()->getUser(), true );
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
	 * @dataProvider provideCustomMentorText
	 */
	public function testRenderMentorText( $mentorsList, $expected ) {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getTestUser( 'sysop' )->getUser();
		$mentee = $this->getMutableTestUser()->getUser();
		$this->insertPage( 'MentorsList', str_replace( '$1', $mentorUser->getName(), $mentorsList ) );
		$mentee->setOption( Mentor::MENTOR_PREF, $mentorUser->getId() );
		$mentee->saveSettings();

		$mentor = Mentor::newFromMentee( $mentee );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $mentee );
		$this->assertEquals( $expected, $mentor->getIntroText( $context ) );
	}

	public function provideCustomMentorText() {
		$fallback = 'This experienced user knows you\'re new and can help you with editing.';
		return [
			[ '[[User:$1]] | This is a sample text.', '"This is a sample text."' ],
			[ '[[User:$1]]|This is a sample text.', '"This is a sample text."' ],
			[ "[[User:$1]]|This is a sample text.\n[[User:Foobar]]|More text", '"This is a sample text."' ],
			[ '[[User:$1]] This is a sample text', $fallback ],
			[ '[[User:$1]]', $fallback ],
			[ "[[User:\$1]]|\n[[User:Foobar]]|This is a sample text.", $fallback ]
		];
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getIntroText
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
		$this->assertStringContainsString(
			'This experienced user knows you\'re new and can help you with editing.',
			$mentor->getIntroText( $context )
		);
	}

	/**
	 * @covers \GrowthExperiments\Mentorship\Mentor::getMentors
	 */
	public function testGetMentorsNoInvalidUsers() {
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$mentorUser = $this->getMutableTestUser()->getUser();
		$secondMentor = $this->getMutableTestUser()->getUser();
		$this->insertPage(
			'MentorsList',
			'[[User:' . $mentorUser->getName() . ']]
			[[User:' . $secondMentor->getName() . ']]
			[[User:This user does not exist]]'
		);
		$availableMentors = Mentor::getMentors();
		$expected = [
			$mentorUser->getTitleKey(),
			$secondMentor->getTitleKey()
		];
		$this->assertArrayEquals( [
			$mentorUser->getTitleKey(),
			$secondMentor->getTitleKey(),
		], $availableMentors );
	}
}
