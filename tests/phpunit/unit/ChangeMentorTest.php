<?php

namespace GrowthExperiments;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Store\MentorStore;
use LogPager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
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
				new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/', '' ),
				$this->getLogPagerMock(),
				$this->getMentorStoreMock(),
				$this->getUserFactoryMock()
			)
		);
	}

	/**
	 * @covers ::wasMentorChanged
	 */
	public function testWasMentorChangedSuccess() {
		$logPagerMock = $this->getLogPagerMock();
		$resultMock = $this->getMockBuilder( IResultWrapper::class )
			->getMock();
		$resultMock->method( 'fetchRow' )->willReturn( [ 'foo' ] );
		$logPagerMock->method( 'getResult' )->willReturn( $resultMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/', '' ),
			$logPagerMock,
			$this->getMentorStoreMock(),
			$this->getUserFactoryMock()
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
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/', '' ),
			$this->getLogPagerMock(),
			$this->getMentorStoreMock(),
			$this->getUserFactoryMock()
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
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/', '' ),
			$this->getLogPagerMock(),
			$this->getMentorStoreMock(),
			$this->getUserFactoryMock()
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
			new Mentor( $this->getUserMock( 'SameMentor', 3 ), 'o/', '' ),
			$this->getLogPagerMock(),
			$this->getMentorStoreMock(),
			$this->getUserFactoryMock()
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
			new Mentor( $this->getUserMock( 'SameMentor', 3 ), 'o/', '' ),
			$this->getLogPagerMock(),
			$this->getMentorStoreMock(),
			$this->getUserFactoryMock()
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

	/**
	 * @return MockObject|LogPager
	 */
	private function getLogPagerMock() {
		return $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return MockObject|MentorStore
	 */
	private function getMentorStoreMock() {
		return $this->getMockBuilder( MentorStore::class )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
	}

	/**
	 * @return MockObject|UserFactory
	 */
	private function getUserFactoryMock() {
		return $this->getMockBuilder( UserFactory::class )
			->disableOriginalConstructor()
			->getMock();
	}

}
