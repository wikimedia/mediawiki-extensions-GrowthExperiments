<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * @group API
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Api\ApiQueryMentorStatus
 */
class ApiQueryMentorStatusTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Prevent caching of MediaWiki:GrowthMentors.json
		$this->setMainCache( CACHE_NONE );
	}

	private function addMentor( UserIdentity $user ): void {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood( $geServices->getMentorWriter()->addMentor(
			$geServices->getMentorProvider()->newMentorFromUserIdentity( $user ),
			$user,
			''
		) );
	}

	/**
	 * @covers ::execute
	 */
	public function testAnonymousUserCannotExecute() {
		$this->expectApiErrorCode( 'permissiondenied-generic' );
		$this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus',
			],
			null,
			new User()
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNotMentorCannotExecute() {
		$this->expectApiErrorCode( 'permissiondenied-generic' );
		$this->doApiRequestWithToken(
			[
				'action' => 'query',
				'meta' => 'growthmentorstatus',
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
		$this->addMentor( $mentor );

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
		$this->addMentor( $mentor );

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
