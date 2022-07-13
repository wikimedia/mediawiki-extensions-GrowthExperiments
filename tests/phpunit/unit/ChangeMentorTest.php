<?php

namespace GrowthExperiments;

use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Store\MentorStore;
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
			$this->createMock( MentorStore::class ),
			$this->createMock( UserFactory::class )
		);
		$status = $changeMentor->execute( $this->getUserMock( 'SameMentor', 3 ), 'test' );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-claimmentee-already-mentor' ) );
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
