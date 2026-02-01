<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\UserIdentity;

/**
 * @group API
 * @group Database
 * @group medium
 * @coversDefaultClass \GrowthExperiments\Api\ApiQueryMentorMentee
 */
class ApiQueryMentorMenteeTest extends ApiTestCase {

	/**
	 * @covers ::execute
	 */
	public function testGetMenteesByName() {
		$mentor = $this->getMutableTestUser()->getUserIdentity();
		$mentees = [
			$this->getMutableTestUser()->getUserIdentity(),
			$this->getMutableTestUser()->getUserIdentity(),
			$this->getMutableTestUser()->getUserIdentity(),
		];
		$store = GrowthExperimentsServices::wrap( $this->getServiceContainer() )->getMentorStore();
		foreach ( $mentees as $mentee ) {
			$store->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );
		}

		$response = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'growthmentormentee',
				'gemmmentor' => $mentor->getName(),
			]
		);
		$this->assertEquals( $mentor->getName(), $response[0]['growthmentormentee']['mentor'] );
		$this->assertArrayEquals(
			array_map( static function ( UserIdentity $user ) {
				return $user->getName();
			}, $mentees ),
			array_column( $response[0]['growthmentormentee']['mentees'], 'name' )
		);
		$this->assertArrayEquals(
			array_map( static fn ( UserIdentity $user ) => $user->getId(), $mentees ),
			array_column( $response[0]['growthmentormentee']['mentees'], 'id' )
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testNoMentees() {
		$mentor = $this->getMutableTestUser()->getUserIdentity();
		$response = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'growthmentormentee',
				'gemmmentor' => $mentor->getName(),
			]
		);
		$this->assertEquals( $mentor->getName(), $response[0]['growthmentormentee']['mentor'] );
		$this->assertArrayEquals( [], $response[0]['growthmentormentee']['mentees'] );
	}
}
