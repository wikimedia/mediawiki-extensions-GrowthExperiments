<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\ReassignMentees;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDatabase;

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
		$dbw = $this->createMock( IDatabase::class );
		$dbw->method( 'lock' )
			->willReturn( true );
		$dbw->method( 'unlock' )
			->willReturn( true );

		$user = $this->createNoOpMock( User::class, [ 'isHidden' ] );
		$user->method( 'isHidden' )
			->willReturn( false );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $user );

		return new ReassignMentees(
			new NullLogger(),
			$dbw,
			$mentorManagerMock ?? $this->createNoOpMock( IMentorManager::class ),
			$mentorStoreMock ?? $this->createNoOpMock( MentorStore::class ),
			$changeMentorFactoryMock ?? $this->createNoOpMock( ChangeMentorFactory::class ),
			$this->createNoOpMock( JobQueueGroupFactory::class ),
			$userFactory,
			$mentor,
			$mentor,
			$contextMock ?? $this->createNoOpMock( IContextSource::class )
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

		$menteeUser = $this->createNoOpMock( User::class, [ 'isHidden' ] );
		$menteeUser->method( 'isHidden' )
			->willReturn( false );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $menteeUser );

		$dbw = $this->createMock( IDatabase::class );
		$dbw->method( 'lock' )->willReturn( true );
		$dbw->method( 'unlock' )->willReturn( true );

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

		$reassignMentees = new ReassignMentees(
			new NullLogger(),
			$dbw,
			$mentorManager,
			$mentorStore,
			$changeMentorFactory,
			$this->createNoOpMock( JobQueueGroupFactory::class ),
			$userFactory,
			$mentor,
			$mentor,
			$context
		);

		$this->assertTrue( $reassignMentees->doReassignMentees( null, 'foo' ) );
	}
}
