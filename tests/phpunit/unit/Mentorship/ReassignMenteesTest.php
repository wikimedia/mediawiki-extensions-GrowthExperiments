<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\ReassignMentees;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\ReassignMentees
 */
class ReassignMenteesTest extends MediaWikiUnitTestCase {

	/**
	 * @param UserIdentity $mentor
	 * @param MentorManager|null $mentorManagerMock
	 * @param MentorProvider|null $mentorProviderMock
	 * @param MentorStore|null $mentorStoreMock
	 * @param ChangeMentorFactory|null $changeMentorFactoryMock
	 * @param IContextSource|null $contextMock
	 * @return ReassignMentees
	 */
	private function newReassignMentees(
		UserIdentity $mentor,
		?MentorManager $mentorManagerMock = null,
		?MentorProvider $mentorProviderMock = null,
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
			$dbw,
			$mentorManagerMock ?? $this->createNoOpMock( MentorManager::class ),
			$mentorProviderMock ?? $this->createNoOpMock( MentorProvider::class ),
			$mentorStoreMock ?? $this->createNoOpMock( MentorStore::class ),
			$changeMentorFactoryMock ?? $this->createNoOpMock( ChangeMentorFactory::class ),
			$this->createNoOpMock( JobQueueGroupFactory::class ),
			$this->createNoOpMock( StatusFormatter::class ),
			$mentor,
			$mentor,
			$contextMock ?? $this->createNoOpMock( IContextSource::class )
		);
	}

	/**
	 * @covers ::doReassignMentees
	 */
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
		$mentorManager = $this->createMock( MentorManager::class );
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
			null,
			$mentorStore,
			$changeMentorFactory,
			$context
		);

		$this->assertTrue( $reassignMentees->doReassignMentees( 'foo' ) );
	}
}
