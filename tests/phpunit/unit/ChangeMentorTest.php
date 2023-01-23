<?php

namespace GrowthExperiments;

use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Tests\ChangeMentorForTests;
use LogPager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Status;
use Wikimedia\Rdbms\IResultWrapper;
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
					MentorWeightManager::WEIGHT_NORMAL
				),
				$this->createMock( LogPager::class ),
				$this->createMock( MentorManager::class ),
				$this->createMock( MentorStore::class ),
				$this->createMock( UserFactory::class )
			)
		);
	}

	/**
	 * @covers ::wasMentorChanged
	 */
	public function testWasMentorChangedSuccess() {
		$logPagerMock = $this->createMock( LogPager::class );
		$resultMock = $this->createMock( IResultWrapper::class );
		$resultMock->method( 'fetchRow' )->willReturn( [ 'foo' ] );
		$logPagerMock->method( 'getResult' )->willReturn( $resultMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor(
				$this->getUserMock( 'OldMentor', 3 ),
				'o/',
				'',
				true,
				MentorWeightManager::WEIGHT_NORMAL
			),
			$logPagerMock,
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
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
				MentorWeightManager::WEIGHT_NORMAL
			),
			$this->createMock( LogPager::class ),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
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
				MentorWeightManager::WEIGHT_NORMAL
			),
			$this->createMock( LogPager::class ),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
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
				MentorWeightManager::WEIGHT_NORMAL
			),
			$this->createMock( LogPager::class ),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
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
				MentorWeightManager::WEIGHT_NORMAL
			),
			$this->createMock( LogPager::class ),
			$this->createMock( MentorManager::class ),
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
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
		$mentorManagerMock->expects( $this->once() )
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
				MentorWeightManager::WEIGHT_NORMAL
			),
			$this->createMock( LogPager::class ),
			$mentorManagerMock,
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
		);
		$changeMentor->execute( $this->getUserMock( 'NewMentor', 4 ), 'test' );
	}

	public function provideExecuteMenteeStatus() {
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
