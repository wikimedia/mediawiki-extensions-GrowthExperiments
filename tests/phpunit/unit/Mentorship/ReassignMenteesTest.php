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

		return new ReassignMentees(
			new NullLogger(),
			$dbw,
			$mentorManagerMock ?? $this->createNoOpMock( IMentorManager::class ),
			$mentorStoreMock ?? $this->createNoOpMock( MentorStore::class ),
			$changeMentorFactoryMock ?? $this->createNoOpMock( ChangeMentorFactory::class ),
			$this->createNoOpMock( JobQueueGroupFactory::class ),
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
			->method( 'getRandomAutoAssignedMentor' )
			->willReturnMap( array_map(
				static fn ( $el ) => [ $el, [], $newMentor ],
				$mentees
			) );
		$mentorStore = $this->createMock( MentorStore::class );
		$mentorStore->expects( $this->once() )
			->method( 'getMenteesByMentor' )
			->with( $mentor, MentorStore::ROLE_PRIMARY )
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
}
