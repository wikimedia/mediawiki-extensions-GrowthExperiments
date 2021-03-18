<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\Constraint\Constraint;
use User;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Api\ApiSetMentor
 */
class ApiSetMentorTest extends ApiTestCase {
	/**
	 * @covers \GrowthExperiments\Api\ApiSetMentor::getAllowedParams
	 */
	public function testRequiredParams() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "mentee" parameter must be set.' );

		$this->doApiRequestWithToken(
			[ 'action' => 'growthsetmentor' ],
			null,
			$this->getTestSysop()->getUser()
		);
	}

	/**
	 * @covers \GrowthExperiments\API\ApiSetMentor::execute
	 */
	public function testAnonCannotSetMentor() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( "You don't have permission to set user's mentor." );
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			new User()
		);
	}

	/**
	 * @covers \GrowthExperiments\API\ApiSetMentor::execute
	 */
	public function testNormalUserCannotSetMentor() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( "You don't have permission to set user's mentor." );
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$performer = $this->getMutableTestUser()->getUser();
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			$performer
		);
	}

	/**
	 * @covers \GrowthExperiments\API\ApiSetMentor::execute
	 */
	public function testSetMentorBySysop() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$mockMentorManager = $this->getMockMentorManager( $mentee, $mentor );
		$mockMentorManager->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorManager', $mockMentorManager );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertEquals( $response[0]['growthsetmentor']['status'], 'ok' );
	}

	/**
	 * @covers \GrowthExperiments\API\ApiSetMentor::execute
	 */
	public function testSetMentorByMentee() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$mockMentorManager = $this->getMockMentorManager( $mentee, $mentor );
		$mockMentorManager->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorManager', $mockMentorManager );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			$mentee
		);
		$this->assertEquals( $response[0]['growthsetmentor']['status'], 'ok' );
	}

	/**
	 * @covers \GrowthExperiments\API\ApiSetMentor::execute
	 */
	public function testSetMentorByMentor() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$mockMentorManager = $this->getMockMentorManager( $mentee, $mentor );
		$mockMentorManager->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorManager', $mockMentorManager );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			$mentor
		);
		$this->assertEquals( $response[0]['growthsetmentor']['status'], 'ok' );
	}

	private function getMockMentorManager( UserIdentity $mentee, UserIdentity $mentor ) {
		$oldMentor = $this->getMutableTestUser()->getUser();
		$mentorManager = $this->getMockBuilder( StaticMentorManager::class )
			->setConstructorArgs( [ [ $mentee->getName() => new Mentor( $oldMentor, '' ) ] ] )
			->setMethods( [ 'setMentorForUser' ] )
			->getMock();
		return $mentorManager;
	}

	private function ruleUserEquals( UserIdentity $user ) {
		return new class( $user ) extends Constraint {
			private $user;

			public function __construct( UserIdentity $user ) {
				$this->user = $user;
			}

			public function toString(): string {
				return 'is the same as ' . $this->user;
			}

			protected function matches( $other ): bool {
				return $other instanceof UserIdentity && $this->user->equals( $other );
			}
		};
	}

}
