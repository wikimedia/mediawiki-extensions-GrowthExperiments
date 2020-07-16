<?php

namespace GrowthExperiments;

use IContextSource;
use LogPager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Status;
use User;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\ChangeMentor
 */
class ChangeMentorTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( ChangeMentor::class,
			new ChangeMentor(
				$this->getUserMock(),
				$this->getUserMock(),
				$this->getContextMock(),
				new NullLogger(),
				$this->getMentorMock(),
				$this->getLogPagerMock()
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
			$this->getUserMock(),
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$this->getMentorMock(),
			$logPagerMock
		);
		$this->assertNotFalse( $changeMentor->wasMentorChanged() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateMenteeIdZero(): void {
		$mentee = $this->getUserMock();
		$mentee->method( 'getId' )
			->willReturn( 0 );
		$changeMentor = new ChangeMentor(
			$mentee,
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$this->getMentorMock(),
			$this->getLogPagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
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
			$this->getUserMock(),
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$this->getMentorMock(),
			$this->getLogPagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertTrue( $status->isGood() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateOldMentorNewMentorEquality(): void {
		$mentorUser = new UserIdentityValue( 1, 'Foo', 1 );
		$newMentor = new UserIdentityValue( 1, 'Foo', 1 );
		$mentorMock = $this->getMentorMock();
		$mentorMock->method( 'getMentorUser' )
			->willReturn( $mentorUser );
		$changeMentor = new ChangeMentor(
			$this->getUserMock(),
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$mentorMock,
			$this->getLogPagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $newMentor;
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertFalse( $status->isGood() );
	}

	/**
	 * @covers ::execute
	 * @covers ::validate
	 */
	public function testExecuteBadStatus(): void {
		$mentorUser = $this->getUserMock();
		$newMentor = $this->getUserMock();
		$mentorUser->method( 'equals' )->with( $newMentor )->willReturn( true );
		$newMentor->method( 'equals' )->with( $mentorUser )->willReturn( true );

		$mentorMock = $this->getMentorMock();
		$mentorMock->method( 'getMentorUser' )
			->willReturn( $mentorUser );
		$changeMentor = new ChangeMentor(
			$this->getUserMock(),
			$this->getUserMock(),
			$this->getContextMock(),
			new NullLogger(),
			$mentorMock,
			$this->getLogPagerMock()
		);
		$status = $changeMentor->execute( $newMentor, 'test' );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-claimmentee-already-mentor' ) );
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|User
	 */
	private function getUserMock() {
		return $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|IContextSource
	 */
	private function getContextMock() {
		return $this->getMockBuilder( IContextSource::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|Mentor
	 */
	private function getMentorMock() {
		return $this->getMockBuilder( Mentor::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject|LogPager
	 */
	private function getLogPagerMock() {
		return $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->getMock();
	}

}
