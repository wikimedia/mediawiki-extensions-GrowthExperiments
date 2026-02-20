<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IConnectionProvider;
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
					IMentorWeights::WEIGHT_NORMAL
				),
				$this->createMock( IMentorManager::class ),
				$this->createMock( MentorStore::class ),
				$this->getMockUserFactory(),
				$this->createMock( IConnectionProvider::class )
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
		$connProvider = $this->createMock( IConnectionProvider::class );
		$connProvider->method( 'getReplicaDatabase' )->willReturn( $dbMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'OldMentor', 3 ),
				'o/',
				'',
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( IMentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->getMockUserFactory(),
			$connProvider
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
				'',
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( IMentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->getMockUserFactory(),
			$this->createMock( IConnectionProvider::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertStatusError( 'growthexperiments-homepage-claimmentee-no-user', $status );
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
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( IMentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->getMockUserFactory(),
			$this->createMock( IConnectionProvider::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertStatusGood( $status );
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
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( IMentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->getMockUserFactory(),
			$this->createMock( IConnectionProvider::class )
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'SameMentor', 3 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertStatusNotGood( $status );
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
				IMentorWeights::WEIGHT_NORMAL
			),
			$this->createMock( IMentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->getMockUserFactory(),
			$this->createMock( IConnectionProvider::class )
		);
		$status = $changeMentor->execute( $this->getUserMock( 'SameMentor', 3 ), 'test' );
		$this->assertStatusError( 'growthexperiments-homepage-claimmentee-already-mentor', $status );
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

		$mentorManagerMock = $this->createMock( IMentorManager::class );
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
				IMentorWeights::WEIGHT_NORMAL
			),
			$mentorManagerMock,
			$mentorStoreMock,
			$this->getMockUserFactory(),
			$this->createMock( IConnectionProvider::class )
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
			$this->assertStatusError( $expectedError, $result );
		}
	}

	public static function provideExecuteMenteeStatus() {
		return [
			'enabled, nonbulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => true,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'enabled, bulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => true,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
			'enabled, no mentorship' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'isMentorshipEnabled' => false,
				'bulkChange' => false,
			],
			'disabled, nonbulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_ENABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_DISABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'disabled, bulk' => [
				'expectedError' => null,
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_DISABLED,
				'expectedNotify' => false,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_DISABLED,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
			'opted_out, nonbulk' => [
				'expectedError' => 'growthexperiments-homepage-claimmentee-opt-out',
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_OPTED_OUT,
				'expectedNotify' => false,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_OPTED_OUT,
				'isMentorshipEnabled' => true,
				'bulkChange' => false,
			],
			'opted_out, bulk' => [
				'expectedError' => 'growthexperiments-homepage-claimmentee-opt-out',
				'expectedMentorshipStatus' => IMentorManager::MENTORSHIP_OPTED_OUT,
				'expectedNotify' => false,
				'originalMentorshipStatus' => IMentorManager::MENTORSHIP_OPTED_OUT,
				'isMentorshipEnabled' => true,
				'bulkChange' => true,
			],
		];
	}

	private function getUserMock( string $name, int $id ): UserIdentity {
		return new UserIdentityValue( $id, $name );
	}

}
