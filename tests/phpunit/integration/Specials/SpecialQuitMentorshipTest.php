<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Specials\SpecialQuitMentorship;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\User\User;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialQuitMentorship
 * @group Database
 */
class SpecialQuitMentorshipTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMainCache( CACHE_NONE );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialQuitMentorship(
			$geServices->getMentorProvider(),
			$geServices->getMentorRemover()
		);
	}

	/**
	 * Get an user who is a mentor
	 *
	 * @param User $mentorUser
	 * @return User
	 */
	private function makeUserMentor( User $mentorUser ): User {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentor = $geServices->getMentorProvider()->newMentorFromUserIdentity( $mentorUser );
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor(
			$mentor,
			$mentorUser,
			''
		) );
		return $mentorUser;
	}

	/**
	 * @covers ::preHtml
	 * @covers ::alterForm
	 * @dataProvider provideHasMentees
	 * @param bool $hasMentees
	 */
	public function testGet( bool $hasMentees ) {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();
		$mentorProvider = $geServices->getMentorProvider();

		$mentorUser = $this->makeUserMentor( $this->getMutableTestUser()->getUser() );

		if ( $hasMentees ) {
			$mentee = $this->getMutableTestUser()->getUser();
			$mentorStore->setMentorForUser( $mentee, $mentorUser, MentorStore::ROLE_PRIMARY );
			$this->assertTrue( $mentorProvider->isMentor( $mentorUser ) );
		}

		/** @var string $html */
		[ $html, ] = $this->executeSpecialPage( '', null, null, $mentorUser );
		$this->assertStringContainsString(
			'growthexperiments-quit-mentorship-reassign-mentees-confirm',
			$html
		);
		$this->assertStringContainsString(
			'growthexperiments-quit-mentorship-has-mentees-pretext',
			$html
		);
	}

	/**
	 * @covers ::onSubmit
	 * @dataProvider provideHasMentees
	 * @param bool $hasMentees
	 */
	public function testPost( bool $hasMentees ) {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorStore = $geServices->getMentorStore();
		$mentorProvider = $geServices->getMentorProvider();

		$mentorUser = $this->makeUserMentor( $this->getMutableTestUser()->getUser() );
		$otherMentor = $this->makeUserMentor( $this->getMutableTestUser()->getUser() );
		$mentee = $this->getMutableTestUser()->getUser();

		if ( $hasMentees ) {
			$mentorStore->setMentorForUser( $mentee, $mentorUser, MentorStore::ROLE_PRIMARY );
		} else {
			$mentorStore->setMentorForUser( $mentee, $otherMentor, MentorStore::ROLE_PRIMARY );
		}

		$this->assertTrue( $mentorProvider->isMentor( $mentorUser ) );
		$this->assertTrue( $mentorProvider->isMentor( $otherMentor ) );

		$request = new FauxRequest( [
			'wpreason' => 'foo bar'
		], true );
		$request->response()->header( 'Status: 200 OK', true, 200 );

		/** @var string $html */
		/** @var FauxResponse $response */
		[ $html, $response ] = $this->executeSpecialPage( '', $request, null, $mentorUser );
		$queueResponse = $this->getServiceContainer()->getJobRunner()->run( [
			'type' => 'reassignMenteesJob',
			'maxJobs' => 1,
			'maxTime' => 3,
		] );

		// job is only submitted when there are any mentees assigned
		if ( $hasMentees ) {
			$this->assertSame( 'job-limit', $queueResponse['reached'] );
		} else {
			$this->assertSame( 'none-ready', $queueResponse['reached'] );
		}

		$this->assertEquals( 200, $response->getStatusCode() );
		$this->assertStringContainsString(
			'growthexperiments-quit-mentorship-success',
			$html
		);
		$this->assertFalse( $mentorProvider->isMentor( $mentorUser ) );
		$this->assertTrue( $mentorProvider->isMentor( $otherMentor ) );
		$this->assertNotEquals(
			$mentorUser->getId(),
			$mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY )->getId()
		);
	}

	public static function provideHasMentees() {
		return [
			'has mentees' => [ true ],
			'no mentees' => [ false ],
		];
	}
}
