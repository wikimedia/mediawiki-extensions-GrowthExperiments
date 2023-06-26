<?php

namespace GrowthExperiments;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Tests\ChangeMentorForTests;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Status;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\ChangeMentor
 */
class ChangeMentorTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( ChangeMentor::class,
			new ChangeMentor(
				$this->getUserMock( 'Mentee', 1 ),
				$this->getUserMock( 'Performer', 2 ),
				new NullLogger(),
				new Mentor(
					$this->getUserMock( 'OldMentor', 3 ),
					'o/',
					'',
					true,
					IMentorWeights::WEIGHT_NORMAL
				),
				$this->createMock( MentorManager::class ),
				$this->createMock( MentorStore::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( IReadableDatabase::class )
			)
		);
	}

	/**
	 * @covers ::wasMentorChanged
	 */
	public function testWasMentorChangedSuccess() {
		$resultMock = $this->createMock( IResultWrapper::class );
		$resultMock->method( 'fetchRow' )->willReturn( [ 'foo' ] );
		$builderMock = $this->createMock( SelectQueryBuilder::class );
		$builderMock->method( $this->anythingBut(
			'fetchResultSet', 'fetchField', 'fetchFieldValues', 'fetchRow',
			'fetchRowCount', 'estimateRowCount'
		) )->willReturnSelf();
		$builderMock->method( 'fetchRow' )->willReturn( $resultMock );
		$dbMock = $this->createMock( IReadableDatabase::class );
		$dbMock->method( 'newSelectQueryBuilder' )->willReturn( $builderMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'OldMentor', 3 ),
				'o/',
				'',
				true,
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$dbMock
		);
		$this->assertNotFalse( $changeMentor->wasMentorChanged() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateMenteeIdZero(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 0 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'OldMentor', 3 ),
				'o/',
				true,
				'',
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IReadableDatabase::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertFalse( $status->isGood() );
		$this->assertSame(
			'growthexperiments-homepage-claimmentee-no-user',
			$status->getErrors()[0]['message']
		);
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateSuccess(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'OldMentor', 3 ),
				'o/',
				'',
				true,
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IReadableDatabase::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertTrue( $status->isGood() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateOldMentorNewMentorEquality(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'SameMentor', 3 ),
				'o/',
				'',
				true,
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IReadableDatabase::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'SameMentor', 3 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertFalse( $status->isGood() );
	}

	/**
	 * @covers ::execute
	 * @covers ::validate
	 */
	public function testExecuteBadStatus(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'SameMentor', 3 ),
				'o/',
				'',
				true,
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IReadableDatabase::class )
		);
		$status = $changeMentor->execute( $this->getUserMock( 'SameMentor', 3 ), 'test' );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-claimmentee-already-mentor' ) );
	}

	/**
	 * @param int $originalStatus
	 * @param int $expectedStatus
	 * @covers ::execute
	 * @dataProvider provideExecuteMenteeStatus
	 */
	public function testExecuteMenteeStatus( int $originalStatus, int $expectedStatus ) {
		$menteeMock = $this->getUserMock( 'Mentee', 1 );

		$mentorManagerMock = $this->createMock( MentorManager::class );
		$mentorManagerMock->expects( $this->atLeastOnce() )
			->method( 'getMentorshipStateForUser' )
			->with( $menteeMock )
			->willReturn( $originalStatus );

		if ( $originalStatus === $expectedStatus ) {
			$mentorManagerMock->expects( $this->never() )
				->method( 'setMentorshipStateForUser' );
		} else {
			$mentorManagerMock->expects( $this->once() )
				->method( 'setMentorshipStateForUser' )
				->with( $menteeMock, $expectedStatus );
		}

		$changeMentor = new ChangeMentorForTests(
			$menteeMock,
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'Mentor', 3 ),
				'o/',
				'',
				true,
				IMentorWeights::WEIGHT_NORMAL
			),
			$mentorManagerMock,
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IReadableDatabase::class )
		);
		$changeMentor->execute( $this->getUserMock( 'NewMentor', 4 ), 'test' );
	}

	public static function provideExecuteMenteeStatus() {
		return [
			'enabled' => [ MentorManager::MENTORSHIP_ENABLED, MentorManager::MENTORSHIP_ENABLED ],
			'disabled' => [ MentorManager::MENTORSHIP_DISABLED, MentorManager::MENTORSHIP_ENABLED ],
			'opt-out' => [ MentorManager::MENTORSHIP_OPTED_OUT, MentorManager::MENTORSHIP_OPTED_OUT ],
		];
	}

	/**
	 * @param string $name
	 * @param int $id
	 * @return UserIdentity
	 */
	private function getUserMock( string $name, int $id ) {
		return new UserIdentityValue( $id, $name );
	}

}
