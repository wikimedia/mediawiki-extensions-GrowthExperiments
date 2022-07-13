<?php

namespace GrowthExperiments\Tests;

use ApiTestCase;
use ApiUsageException;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;

/**
 * @group API
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Api\ApiQueryMentorStatus
 */
class ApiQueryMentorStatusTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgGEMentorDashboardEnabled', true );
		$this->setMwGlobals( 'wgGEHomepageManualAssignmentMentorsList', null );
	}

	/**
	 * @covers ::execute
	 */
	public function testAnonymousUserCannotExecute() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus'
			],
			null,
			new \User()
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNotMentorCannotExecute() {
		$this->insertPage( 'MentorsList', 'no user links here' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus'
			],
			null,
			$this->getTestUser()->getUser()
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testDefaultExecute() {
		$mentor = $this->getTestUser()->getUser();

		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$response = $this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus',
			],
			null,
			$mentor
		);
		$this->assertEquals( MentorStatusManager::STATUS_ACTIVE, $response[0]['growthmentorstatus']['mentorstatus'] );
	}

	/**
	 * @covers ::execute
	 */
	public function testAway() {
		$mentor = $this->getTestUser()->getUser();

		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );

		$mentorStatusManager = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getMentorStatusManager();
		$mentorStatusManager->markMentorAsAway( $mentor, 14 );

		$response = $this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus',
			],
			null,
			$mentor
		);
		$this->assertEquals( MentorStatusManager::STATUS_AWAY, $response[0]['growthmentorstatus']['mentorstatus'] );
	}
}
