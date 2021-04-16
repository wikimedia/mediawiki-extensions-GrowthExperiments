<?php

namespace GrowthExperiments\Tests;

use CachedBagOStuff;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use HashBagOStuff;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Mentorship\Store\MentorStore
 * @covers \GrowthExperiments\Mentorship\Store\DatabaseMentorStore
 */
class DatabaseMentorStoreTest extends MentorStoreTestCase {

	protected function getStore( bool $wasPosted ): MentorStore {
		return new DatabaseMentorStore( $this->getServiceContainer()->getUserFactory(),
			$this->db, $this->db, $wasPosted );
	}

	protected function getJobType(): string {
		return 'setUserMentorDatabaseJob';
	}

	public function testGetSetJob() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( false );

		$cache = new HashBagOStuff();
		$store->setCache( $cache, 1000 );
		/** @var CachedBagOStuff $multiCache */
		$multiCache = TestingAccessWrapper::newFromObject( $store )->cache;
		/** @var HashBagOStuff $inProcessCache */
		$inProcessCache = TestingAccessWrapper::newFromObject( $multiCache )->procCache;

		// Pretend we are in the job runner. This will be needed by the DatabaseMentorStore used by
		// the job, which will actually be called from the job runner, but we need to set the flag
		// before the job is instantiated.
		define( 'MEDIAWIKI_JOB_RUNNER', true );

		$this->assertNull( $store->loadMentorUser( $mentee ) );
		$store->setMentorForUser( $mentee, $mentor );

		// read from in-process cache
		$actualMentor = $store->loadMentorUser( $mentee );
		$this->assertSameUser( $mentor, $actualMentor );

		// read from disk
		// first process the job queue:
		$response = $this->getServiceContainer()->getJobRunner()->run( [
			'type' => $this->getJobType(),
			'maxJobs' => 1,
			'maxTime' => 3,
		] );
		$this->assertSame( 'job-limit', $response['reached'] );
		$actualMentor = $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY, MentorStore::READ_LATEST );
		$this->assertSameUser( $mentor, $actualMentor );
	}

}
