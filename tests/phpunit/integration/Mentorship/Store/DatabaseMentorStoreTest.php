<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Mentorship\Store\MentorStore
 * @covers \GrowthExperiments\Mentorship\Store\DatabaseMentorStore
 */
class DatabaseMentorStoreTest extends MentorStoreTestCase {

	protected function getStore( bool $wasPosted ): MentorStore {
		return new DatabaseMentorStore(
			new NullLogger(),
			$this->wanCache,
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getDBLoadBalancer(),
			$wasPosted
		);
	}

	protected function getJobType(): string {
		return 'setUserMentorDatabaseJob';
	}

	public function testGetSetJob() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$store = $this->getStore( false );

		// Pretend we are in the job runner. This will be needed by the DatabaseMentorStore used by
		// the job, which will actually be called from the job runner, but we need to set the flag
		// before the job is instantiated.
		define( 'MEDIAWIKI_JOB_RUNNER', true );

		$this->assertNull( $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY ) );
		$store->setMentorForUser( $mentee, $mentor, MentorStore::ROLE_PRIMARY );

		// read from in-process cache
		$actualMentor = $store->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
		$this->assertSameUser( $mentor, $actualMentor );

		// read from disk
		// first process the job queue and clear process cache:
		$response = $this->getServiceContainer()->getJobRunner()->run( [
			'type' => $this->getJobType(),
			'maxJobs' => 1,
			'maxTime' => 3,
		] );
		TestingAccessWrapper::newFromObject( $store )->invalidateMentorCache(
			$mentee,
			'primary'
		);
		$this->assertSame( 'job-limit', $response['reached'] );
		$actualMentor = $store->loadMentorUser(
			$mentee,
			MentorStore::ROLE_PRIMARY,
			IDBAccessObject::READ_LATEST
		);
		$this->assertSameUser( $mentor, $actualMentor );
	}

	/**
	 * @param UserIdentity[] $data
	 * @return array
	 */
	private function getIds( array $data ): array {
		return array_map( static function ( $mentee ) {
			return $mentee->getId();
		}, $data );
	}

	public function testGetMenteesByMentor() {
		$store = $this->getStore( true );

		// Prepare users
		$menteeOne = $this->getMutableTestUser()->getUser();
		$menteeTwo = $this->getMutableTestUser()->getUser();
		$menteeThree = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$otherMentor = $this->getMutableTestUser()->getUser();
		$mentorNoMentees = $this->getMutableTestUser()->getUser();

		// Save mentor/mentee relationship
		$store->setMentorForUser( $menteeOne, $mentor, MentorStore::ROLE_PRIMARY );
		$store->setMentorForUser( $menteeTwo, $mentor, MentorStore::ROLE_PRIMARY );
		$store->setMentorForUser( $menteeOne, $otherMentor, MentorStore::ROLE_BACKUP );
		$store->setMentorForUser( $menteeTwo, $otherMentor, MentorStore::ROLE_BACKUP );

		$store->setMentorForUser( $menteeThree, $otherMentor, MentorStore::ROLE_PRIMARY );
		$store->setMentorForUser( $menteeThree, $mentor, MentorStore::ROLE_BACKUP );

		// Test mentees mentored by $mentor
		$this->assertArrayEquals(
			[ $menteeOne->getId(), $menteeTwo->getId() ],
			$this->getIds( $store->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY ) )
		);
		$this->assertArrayEquals(
			[ $menteeThree->getId() ],
			$this->getIds( $store->getMenteesByMentor( $mentor, MentorStore::ROLE_BACKUP ) )
		);

		// Test mentees mentored by $otherMentor
		$this->assertArrayEquals(
			[ $menteeThree->getId() ],
			$this->getIds( $store->getMenteesByMentor( $otherMentor, MentorStore::ROLE_PRIMARY ) )
		);
		$this->assertArrayEquals(
			[ $menteeOne->getId(), $menteeTwo->getId() ],
			$this->getIds( $store->getMenteesByMentor( $otherMentor, MentorStore::ROLE_BACKUP ) )
		);

		// Test mentees mentored by $mentorNoMentees (none expected)
		$this->assertArrayEquals(
			[],
			$this->getIds( $store->getMenteesByMentor( $mentorNoMentees, MentorStore::ROLE_PRIMARY ) )
		);
		$this->assertArrayEquals(
			[],
			$this->getIds( $store->getMenteesByMentor( $mentorNoMentees, MentorStore::ROLE_BACKUP ) )
		);
	}

}
