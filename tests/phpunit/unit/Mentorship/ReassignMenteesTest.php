<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\ReassignMentees;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\JobQueue\JobQueue;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\LockManager\ILockManager;
use Wikimedia\ScopedCallback;

/**
 * @covers \GrowthExperiments\Mentorship\ReassignMentees
 */
class ReassignMenteesTest extends MediaWikiUnitTestCase {

	private function newReassignMentees(
		UserIdentity $mentor,
		?IMentorManager $mentorManagerMock = null,
		?MentorStore $mentorStoreMock = null,
		?ChangeMentorFactory $changeMentorFactoryMock = null,
		?IContextSource $contextMock = null
	): ReassignMentees {
		$user = $this->createNoOpMock( User::class, [ 'isHidden' ] );
		$user->method( 'isHidden' )
			->willReturn( false );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $user );

		$lockManager = $this->createNoOpMock( ILockManager::class, [ 'scopedLock' ] );
		$lockManager->expects( $this->once() )
			->method( 'scopedLock' )
			->with( 'GrowthExperiments-ReassignMentees-123' )
			->willReturn( $this->createNoOpMock( ScopedCallback::class ) );

		return new ReassignMentees(
			new NullLogger(),
			$mentorManagerMock ?? $this->createNoOpMock( IMentorManager::class ),
			$mentorStoreMock ?? $this->createNoOpMock( MentorStore::class ),
			$changeMentorFactoryMock ?? $this->createNoOpMock( ChangeMentorFactory::class ),
			$this->createNoOpMock( JobQueueGroupFactory::class ),
			$userFactory,
			$lockManager,
			$mentor,
			$mentor,
			$contextMock ?? $this->createNoOpMock( IContextSource::class )
		);
	}

	/**
	 * Build a ReassignMentees that records the jobs it pushes.
	 *
	 * @param UserIdentity $mentor
	 * @param UserIdentity $performer
	 * @param JobSpecification[] &$pushedJobs Collects every pushed job
	 */
	private function newReassignMenteesForScheduling(
		UserIdentity $mentor,
		UserIdentity $performer,
		array &$pushedJobs
	): ReassignMentees {
		$jobQueueGroup = $this->createNoOpMock( JobQueueGroup::class, [ 'get', 'lazyPush' ] );
		$jobQueueGroup->method( 'get' )
			->willReturn( $this->createMock( JobQueue::class ) );
		$jobQueueGroup->method( 'lazyPush' )
			->willReturnCallback( static function ( $job ) use ( &$pushedJobs ) {
				$pushedJobs[] = $job;
			} );
		$jobQueueGroupFactory = $this->createNoOpMock(
			JobQueueGroupFactory::class,
			[ 'makeJobQueueGroup' ]
		);
		$jobQueueGroupFactory->method( 'makeJobQueueGroup' )
			->willReturn( $jobQueueGroup );

		return new ReassignMentees(
			new NullLogger(),
			$this->createNoOpMock( IMentorManager::class ),
			$this->createNoOpMock( MentorStore::class ),
			$this->createNoOpMock( ChangeMentorFactory::class ),
			$jobQueueGroupFactory,
			$this->createNoOpMock( UserFactory::class ),
			$this->createNoOpMock( ILockManager::class ),
			$performer,
			$mentor,
			$this->createNoOpMock( IContextSource::class )
		);
	}

	/**
	 * Two reassignments for one mentor must collapse into one job (T322374).
	 *
	 * The queue reads the deduplication options from the pushed object, and the pushed
	 * object is a JobSpecification. It never reads ReassignMenteesJob, so a job class that
	 * declares ignoreDuplicates() does not deduplicate anything (T418194).
	 */
	public function testScheduleReassignMenteesJobDeduplicates() {
		$mentor = new UserIdentityValue( 123, 'Mentor' );
		$otherMentor = new UserIdentityValue( 456, 'Other Mentor' );
		$performer = new UserIdentityValue( 321, 'Performer' );
		$otherPerformer = new UserIdentityValue( 654, 'Other Performer' );

		$pushedJobs = [];
		$this->newReassignMenteesForScheduling( $mentor, $performer, $pushedJobs )
			->scheduleReassignMenteesJob( 'first-message', 'Mentor' );
		$this->newReassignMenteesForScheduling( $mentor, $otherPerformer, $pushedJobs )
			->scheduleReassignMenteesJob( 'second-message', 'Something else' );
		$this->newReassignMenteesForScheduling( $otherMentor, $performer, $pushedJobs )
			->scheduleReassignMenteesJob( 'first-message', 'Other Mentor' );

		// scheduleReassignMenteesJob defers the push, to compute the job release
		// timestamp at push time (T418194). Run the queue to get the jobs.
		DeferredUpdates::doUpdates();

		[ $job, $sameMentorOtherPerformer, $otherMentorJob ] = $pushedJobs;

		$this->assertTrue(
			$job->ignoreDuplicates(),
			'the job must opt into deduplication, or the queue keeps every copy'
		);
		$this->assertSame(
			$job->getDeduplicationInfo(),
			$sameMentorOtherPerformer->getDeduplicationInfo(),
			'performer and message must not make two jobs for one mentor look different'
		);
		$this->assertNotSame(
			$job->getDeduplicationInfo(),
			$otherMentorJob->getDeduplicationInfo(),
			'jobs for different mentors must never deduplicate against each other'
		);
	}

	public function testDoReassignMentees() {
		$mentor = new UserIdentityValue( 123, 'Mentor' );
		$newMentor = new UserIdentityValue( 321, 'New Mentor' );
		$mentees = [
			new UserIdentityValue( 1, 'Mentee 1' ),
			new UserIdentityValue( 2, 'Mentee 2' ),
		];

		$msg = $this->createMock( Message::class );
		$msg->expects( $this->exactly( count( $mentees ) ) )
			->method( 'text' )
			->willReturn( 'foo' );
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->exactly( count( $mentees ) ) )
			->method( 'msg' )
			->with( 'foo', $mentor->getName() )
			->willReturn( $msg );
		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorManager->expects( $this->exactly( count( $mentees ) ) )
			->method( 'isUserIneligibleForMentorship' )
			->willReturn( false );
		$mentorManager->expects( $this->exactly( count( $mentees ) ) )
			->method( 'getRandomAutoAssignedMentor' )
			->willReturnMap( array_map(
				static fn ( $el ) => [ $el, [], $newMentor ],
				$mentees
			) );
		$mentorStore = $this->createMock( MentorStore::class );
		$mentorStore->expects( $this->once() )
			->method( 'getMenteesByMentor' )
			->with( $mentor, MentorStore::ROLE_PRIMARY, true )
			->willReturn( $mentees );
		$changeMentor = $this->createMock( ChangeMentor::class );
		$changeMentor->expects( $this->exactly( count( $mentees ) ) )
			->method( 'execute' )
			->with( $newMentor, 'foo' )
			->willReturn( Status::newGood() );
		$changeMentorFactory = $this->createMock( ChangeMentorFactory::class );
		$changeMentorFactory->expects( $this->exactly( count( $mentees ) ) )
			->method( 'newChangeMentor' )
			->willReturnMap( array_map(
				static fn ( $el ) => [ $el, $mentor, $changeMentor ],
				$mentees
			) );
		$reassignMentees = $this->newReassignMentees(
			$mentor,
			$mentorManager,
			$mentorStore,
			$changeMentorFactory,
			$context
		);

		$this->assertTrue( $reassignMentees->doReassignMentees( null, 'foo' ) );
	}

	/**
	 * T418992: Blocked users should not receive a new mentor when their mentor quits
	 */
	public function testDoReassignMenteesDropsBlockedMentees() {
		$mentor = new UserIdentityValue( 123, 'Mentor' );
		$newMentor = new UserIdentityValue( 321, 'New Mentor' );
		$blockedMentee = new UserIdentityValue( 1, 'Blocked Mentee' );
		$normalMentee = new UserIdentityValue( 2, 'Normal Mentee' );
		$mentees = [ $blockedMentee, $normalMentee ];

		$msg = $this->createMock( Message::class );
		$msg->method( 'text' )->willReturn( 'foo' );
		$context = $this->createMock( IContextSource::class );
		$context->expects( $this->once() )
			->method( 'msg' )
			->with( 'foo', $mentor->getName() )
			->willReturn( $msg );

		$mentorManager = $this->createMock( IMentorManager::class );
		$mentorManager->method( 'isUserIneligibleForMentorship' )
			->willReturnCallback( static function ( UserIdentity $identity ) use ( $blockedMentee ) {
				return $identity->getName() === $blockedMentee->getName();
			} );
		$mentorManager->expects( $this->once() )
			->method( 'getRandomAutoAssignedMentor' )
			->with( $normalMentee )
			->willReturn( $newMentor );

		$mentorStore = $this->createMock( MentorStore::class );
		$mentorStore->expects( $this->once() )
			->method( 'getMenteesByMentor' )
			->with( $mentor, MentorStore::ROLE_PRIMARY, true )
			->willReturn( $mentees );
		$mentorStore->expects( $this->once() )
			->method( 'dropMenteeRelationship' )
			->with( $blockedMentee );

		$changeMentor = $this->createMock( ChangeMentor::class );
		$changeMentor->expects( $this->once() )
			->method( 'execute' )
			->with( $newMentor, $this->anything() )
			->willReturn( Status::newGood() );
		$changeMentorFactory = $this->createMock( ChangeMentorFactory::class );
		$changeMentorFactory->expects( $this->once() )
			->method( 'newChangeMentor' )
			->with( $normalMentee, $mentor )
			->willReturn( $changeMentor );

		$reassignMentees = $this->newReassignMentees(
			$mentor,
			$mentorManager,
			$mentorStore,
			$changeMentorFactory,
			$context
		);

		$this->assertTrue( $reassignMentees->doReassignMentees( null, 'foo' ) );
	}
}
