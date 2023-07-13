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
use User;
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
				$this->getMockUserFactory(),
				$this->createMock( IReadableDatabase::class )
			)
		);
	}

	private function getMockUserFactory() {
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturnCallback( function ( $userIdentity ) {
				$user = $this->createMock( User::class );
				$user->method( 'isNamed' )->willReturn( (bool)$userIdentity->getId() );
				return $user;
			} );
		return $userFactory;
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
			$this->getMockUserFactory(),
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
			$this->getMockUserFactory(),
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
			$this->getMockUserFactory(),
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
			$this->getMockUserFactory(),
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
			$this->getMockUserFactory(),
			$this->createMock( IReadableDatabase::class )
		);
		$status = $changeMentor->execute( $this->getUserMock( 'SameMentor', 3 ), 'test' );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-claimmentee-already-mentor' ) );
	}

	/**
	 * @param string|null $expectedError
	 * @param int $expectedMentorshipStatus
	 * @param bool $expectedNotify
	 * @param int $originalMentorshipStatus
	 * @param bool $isMentorshipEnabled
	 * @param bool $bulkChange
	 * @covers ::execute
	 * @dataProvider provideExecuteMenteeStatus
	 */
	public function testExecuteMenteeStatus(
		?string $expectedError,
		int $expectedMentorshipStatus,
		bool $expectedNotify,
		int $originalMentorshipStatus,
		bool $isMentorshipEnabled,
		bool $bulkChange
	) {
		$menteeMock = $this->getUserMock( 'Mentee', 1 );
		$newMentorMock = $this->getUserMock( 'NewMentor', 4 );

		$mentorManagerMock = $this->createMock( MentorManager::class );
		$mentorManagerMock->expects( $this->atLeastOnce() )
			->method( 'getMentorshipStateForUser' )
			->with( $menteeMock )
			->willReturn( $originalMentorshipStatus );

		if ( $originalMentorshipStatus === $expectedMentorshipStatus ) {
			$mentorManagerMock->expects( $this->never() )
				->method( 'setMentorshipStateForUser' );
		} else {
			$mentorManagerMock->expects( $this->once() )
				->method( 'setMentorshipStateForUser' )
				->with( $menteeMock, $expectedMentorshipStatus );
		}

		$mentorStoreMock = $this->createMock( MentorStore::class );
		$mentorStoreMock->expects( $expectedError === null ? $this->once() : $this->never() )
			->method( 'setMentorForUser' )
			->with( $menteeMock, $newMentorMock, MentorStore::ROLE_PRIMARY );

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
			$mentorStoreMock,
			$this->getMockUserFactory(),
			$this->createMock( IReadableDatabase::class )
		);
		$changeMentor->isMentorshipEnabled = $isMentorshipEnabled;

		$result = $changeMentor->execute(
			$newMentorMock,
			'test',
			$bulkChange
		);

		$this->assertSame( $expectedNotify, $changeMentor->didNotify );
		if ( $expectedError === null ) {
			$this->assertTrue( $result->isOK() );
		} else {
			$this->assertTrue( $result->hasMessage( $expectedError ) );
			$this->assertFalse( $result->isOK() );
		}
	}

	public static function provideExecuteMenteeStatus() {
		return [
			'enabled, nonbulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => true,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'enabled, bulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => true,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
			'enabled, no mentorship' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => false,
				'bulkChange' => false,
			],
			'disabled, nonbulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_DISABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'disabled, bulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_DISABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_DISABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
			'opted_out, nonbulk' => [
				'expectedError' => 'growthexperiments-homepage-claimmentee-opt-out',
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_OPTED_OUT,
				'expectedNotify' => false,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_OPTED_OUT,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'opted_out, bulk' => [
				'expectedError' => 'growthexperiments-homepage-claimmentee-opt-out',
				'expectedMentorshipStatus' => MentorManager::MENTORSHIP_OPTED_OUT,
				'expectedNotify' => false,
				'originalMentorshipStatus' => MentorManager::MENTORSHIP_OPTED_OUT,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
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
