<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\QuitMentorship;
use GrowthExperiments\Mentorship\Store\MentorStore;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\QuitMentorship
 */
class QuitMentorshipTest extends MediaWikiUnitTestCase {

	/**
	 * @param UserIdentity $mentor
	 * @param MentorManager|null $mentorManagerMock
	 * @param MentorProvider|null $mentorProviderMock
	 * @param MentorStore|null $mentorStoreMock
	 * @param ChangeMentorFactory|null $changeMentorFactoryMock
	 * @param IContextSource|null $contextMock
	 * @return QuitMentorship
	 */
	private function newQuitMentorship(
		UserIdentity $mentor,
		?MentorManager $mentorManagerMock = null,
		?MentorProvider $mentorProviderMock = null,
		?MentorStore $mentorStoreMock = null,
		?ChangeMentorFactory $changeMentorFactoryMock = null,
		?IContextSource $contextMock = null
	) {
		return new QuitMentorship(
			$mentorManagerMock ?? $this->createNoOpMock( MentorManager::class ),
			$mentorProviderMock ?? $this->createNoOpMock( MentorProvider::class ),
			$mentorStoreMock ?? $this->createNoOpMock( MentorStore::class ),
			$changeMentorFactoryMock ?? $this->createNoOpMock( ChangeMentorFactory::class ),
			$this->createNoOpMock( PermissionManager::class, [ 'addTemporaryUserRights' ] ),
			$this->createNoOpMock( JobQueueGroupFactory::class ),
			$mentor,
			$contextMock ?? $this->createNoOpMock( IContextSource::class )
		);
	}

	/**
	 * @covers ::getStage
	 * @param int $expectedStage
	 * @param bool $isMentor
	 * @param bool $hasMentees
	 * @dataProvider provideGetStage
	 */
	public function testGetStage( int $expectedStage, bool $isMentor, bool $hasMentees ) {
		$mentor = new UserIdentityValue( 123, 'Mentor' );
		$mentorProvider = $this->createMock( MentorProvider::class );
		$mentorProvider->expects( $this->once() )
			->method( 'isMentor' )
			->with( $mentor )
			->willReturn( $isMentor );
		$mentorStore = $this->createMock( MentorStore::class );
		$mentorStore->expects( $isMentor ? $this->never() : $this->once() )
			->method( 'hasAnyMentees' )
			->with( $mentor, MentorStore::ROLE_PRIMARY )
			->willReturn( $hasMentees );

		$quitMentorship = $this->newQuitMentorship(
			$mentor,
			null,
			$mentorProvider,
			$mentorStore
		);

		$this->assertEquals(
			$expectedStage,
			$quitMentorship->getStage()
		);
	}

	public function provideGetStage() {
		return [
			'isMentorHasMentees' => [ QuitMentorship::STAGE_LISTED_AS_MENTOR, true, true ],
			'isMentorNoMentees' => [ QuitMentorship::STAGE_LISTED_AS_MENTOR, true, false ],
			'notMentorHasMentees' => [ QuitMentorship::STAGE_NOT_LISTED_HAS_MENTEES, false, true ],
			'notMentorNoMentees' => [ QuitMentorship::STAGE_NOT_LISTED_NO_MENTEES, false, false ],
		];
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

		$msg = $this->createMock( \Message::class );
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
			->withConsecutive( ...array_map(
				static function ( $el ) {
					return [ $el ];
				},
				$mentees
			) )
			->willReturn( $newMentor );
		$mentorStore = $this->createMock( MentorStore::class );
		$mentorStore->expects( $this->once() )
			->method( 'getMenteesByMentor' )
			->with( $mentor, MentorStore::ROLE_PRIMARY )
			->willReturn( $mentees );
		$changeMentor = $this->createMock( ChangeMentor::class );
		$changeMentor->expects( $this->exactly( count( $mentees ) ) )
			->method( 'execute' )
			->with( $newMentor, 'foo' );
		$changeMentorFactory = $this->createMock( ChangeMentorFactory::class );
		$changeMentorFactory->expects( $this->exactly( count( $mentees ) ) )
			->method( 'newChangeMentor' )
			->withConsecutive( ...array_map(
				static function ( $el ) use ( $mentor, $context ) {
					return [ $el, $mentor, $context ];
				},
				$mentees
			) )
			->willReturn( $changeMentor );
		$quitMentorship = $this->newQuitMentorship(
			$mentor,
			$mentorManager,
			null,
			$mentorStore,
			$changeMentorFactory,
			$context
		);

		$this->assertTrue( $quitMentorship->doReassignMentees( 'foo' ) );
	}
}