<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
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

		$this->doApiRequest(
			[ 'action' => 'growthsetmentor' ],
			null,
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
		$response = $this->doApiRequest(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
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
		$response = $this->doApiRequest(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
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
		$response = $this->doApiRequest(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
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
		$response = $this->doApiRequest(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
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
		$response = $this->doApiRequest(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName()
			],
			null,
			null,
			$mentor
		);
		$this->assertEquals( $response[0]['growthsetmentor']['status'], 'ok' );
	}
}
