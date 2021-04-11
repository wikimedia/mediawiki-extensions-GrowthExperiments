<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Mentorship\Store\DatabaseMentorStore
 */
class DatabaseMentorStoreTest extends MediaWikiIntegrationTestCase {

	public function testGetSet() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore();

		$this->assertNull( $store->loadMentorUser( $mentee ) );
		$store->setMentorForUser( $mentee, $mentor );
		$actualMentor = $store->loadMentorUser( $mentee );
		$this->assertNotNull( $actualMentor );
		$this->assertSame( $mentor->getId(), $actualMentor->getId() );
	}

	private function getStore(): DatabaseMentorStore {
		return new DatabaseMentorStore( $this->getServiceContainer()->getUserFactory(),
			$this->db, $this->db, true );
	}

}
